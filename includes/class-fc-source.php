<?php
/**
 * FluentCRM-as-source: leest een bestaand FluentCRM contact en mergt de
 * standaardvelden + custom_values in de PSFID resolver fields.
 *
 * Wordt alleen geladen als FluentCRM actief is (function_exists FluentCrmApi).
 * Bij conflict tussen PSFID-token data en FC custom_values wint FluentCRM, want
 * FC is de autoritatieve bron voor contactgegevens (langer bewaard, centraal
 * beheerd, vanuit FC ge-edit). PSFID-token blijft leidend voor identificatie.
 *
 * Filterhook voor whitelist via code:
 *   add_filter( 'psfid_fc_source_fields', function ( $velden, $contact, $email ) {
 *       return array_intersect_key( $velden, array_flip( [ 'first_name', 'phone' ] ) );
 *   }, 10, 3 );
 */

defined( 'ABSPATH' ) || exit;

class PSFID_FC_Source {

    /**
     * Standaard FluentCRM contact-velden die in PSFID gemerged worden.
     */
    private const STANDAARD_VELDEN = [
        'first_name',
        'last_name',
        'phone',
        'company',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'city',
        'state',
        'country',
    ];

    /**
     * Is FC-source-merge ingeschakeld?
     * Default aan zodra FluentCRM beschikbaar is, opt-out via setting.
     */
    public static function is_enabled(): bool {
        if ( ! function_exists( 'FluentCrmApi' ) ) {
            return false;
        }
        $settings = PSFID_Plugin::get_settings();
        return ! isset( $settings['fc_source_enabled'] ) || (bool) $settings['fc_source_enabled'];
    }

    /**
     * Haal alle relevante FC-velden op voor een email.
     * Resultaat is een associatieve array klaar voor merge in PSFID_Resolver fields.
     * Lege FC-waardes worden NIET teruggegeven, zodat ze PSFID-data niet overschrijven.
     *
     * @param string $email
     * @return array  key => value
     */
    public static function haal_velden_op( string $email ): array {
        if ( ! self::is_enabled() ) {
            return [];
        }

        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return [];
        }

        try {
            $api     = FluentCrmApi( 'contacts' );
            $contact = $api->getContact( $email );
            if ( ! $contact ) {
                return [];
            }

            $velden = [];

            // Standaard contact-velden
            foreach ( self::STANDAARD_VELDEN as $key ) {
                $val = isset( $contact->{$key} ) ? $contact->{$key} : null;
                if ( $val !== null && $val !== '' ) {
                    $velden[ $key ] = (string) $val;
                }
            }

            // Email is altijd autoritatief vanuit FC
            if ( ! empty( $contact->email ) ) {
                $velden['email'] = (string) $contact->email;
            }

            // Custom values mergen
            if ( ! empty( $contact->custom_values ) ) {
                foreach ( (array) $contact->custom_values as $key => $val ) {
                    if ( ! is_scalar( $val ) || $val === null || $val === '' ) {
                        continue;
                    }
                    $clean_key = sanitize_key( strtolower( (string) $key ) );
                    if ( $clean_key === '' ) {
                        continue;
                    }
                    $velden[ $clean_key ] = (string) $val;
                }
            }

            /**
             * Filter de FC-velden voor PSFID-merge. Whitelist mogelijk door een
             * subset terug te geven.
             *
             * @param array  $velden  Key => value array van FC-velden
             * @param object $contact Het FluentCRM contact-object
             * @param string $email   Het email-adres waarop gezocht is
             */
            return (array) apply_filters( 'psfid_fc_source_fields', $velden, $contact, $email );

        } catch ( \Throwable $e ) {
            if ( class_exists( 'PSFID_Log' ) ) {
                PSFID_Log::schrijf( null, 'fc_source_error', $e->getMessage() );
            }
            return [];
        }
    }

    /**
     * Helper: zoek FC subscriber_id voor een email.
     */
    public static function subscriber_id_voor_email( string $email ): ?int {
        if ( ! function_exists( 'FluentCrmApi' ) ) {
            return null;
        }
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return null;
        }
        try {
            $contact = FluentCrmApi( 'contacts' )->getContact( $email );
            return $contact && ! empty( $contact->id ) ? (int) $contact->id : null;
        } catch ( \Throwable $e ) {
            return null;
        }
    }

    /**
     * Zoek het email-adres van een FluentCRM contact op basis van een custom field
     * slug + waarde. Voor de ?rid= live-resolve: een URL-parameter bevat de waarde
     * van een custom field (bijv. random_userid), en wij zoeken het contact erbij.
     *
     * Auto-detect tussen de twee mogelijke meta-tabellen, want de tabel- en
     * kolomnamen verschillen per FluentCRM-versie en installatie:
     *   - fc_subscriber_meta  met kolommen  key   / value
     *   - fc_contact_meta     met kolommen  meta_key / meta_value
     *
     * @param string $slug   Custom field slug (bijv. random_userid)
     * @param string $waarde De waarde uit de URL
     * @return string|null    Email of null
     */
    public static function vind_email_op_custom_field( string $slug, string $waarde ): ?string {
        if ( ! function_exists( 'FluentCrmApi' ) ) {
            return null;
        }
        $slug   = sanitize_key( $slug );
        $waarde = trim( $waarde );
        if ( $slug === '' || $waarde === '' ) {
            return null;
        }

        $subscriber_id = self::zoek_subscriber_id_op_meta( $slug, $waarde );
        if ( ! $subscriber_id ) {
            return null;
        }

        try {
            $contact = FluentCrmApi( 'contacts' )->getContact( $subscriber_id );
            return $contact && ! empty( $contact->email )
                ? sanitize_email( (string) $contact->email )
                : null;
        } catch ( \Throwable $e ) {
            if ( class_exists( 'PSFID_Log' ) ) {
                PSFID_Log::schrijf( null, 'fc_source_error', 'rid lookup getContact: ' . $e->getMessage() );
            }
            return null;
        }
    }

    /**
     * Probeer de subscriber_id te vinden in de mogelijke FluentCRM meta-tabellen.
     * Itereert over de bekende tabel- en kolomvarianten en neemt de eerste hit.
     */
    private static function zoek_subscriber_id_op_meta( string $slug, string $waarde ): int {
        global $wpdb;

        // [ tabel, key-kolom, value-kolom ]
        $kandidaten = [
            [ $wpdb->prefix . 'fc_subscriber_meta', 'key', 'value' ],
            [ $wpdb->prefix . 'fc_contact_meta',    'meta_key', 'meta_value' ],
        ];

        foreach ( $kandidaten as $kandidaat ) {
            [ $tabel, $key_col, $val_col ] = $kandidaat;

            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabel ) ) !== $tabel ) {
                continue;
            }

            // Kolomnamen kunnen reserved words zijn (key/value): backticks verplicht.
            // Tabel- en kolomnamen komen uit onze eigen whitelist hierboven, niet uit user input.
            $id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT subscriber_id FROM `$tabel` WHERE `$key_col` = %s AND `$val_col` = %s LIMIT 1",
                $slug,
                $waarde
            ) );

            if ( $id > 0 ) {
                return $id;
            }
        }

        return 0;
    }
}
