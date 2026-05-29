<?php
/**
 * FluentCRM bridge: bij capture, contact aanmaken/updaten en token bewaren als custom field.
 */

defined( 'ABSPATH' ) || exit;

class PSFID_CRM_FluentCRM {

    public function __construct() {
        add_action( 'psfid/token_captured', [ $this, 'sync' ], 20, 5 );
    }

    public function sync( int $token_id, string $token, array $data, string $bron, $form_id ): void {
        if ( ! function_exists( 'FluentCrmApi' ) ) {
            return;
        }

        $email = $this->extract_email( $data );
        if ( ! $email ) {
            return;
        }

        $slug = self::token_field_slug();

        try {
            $payload = [
                'email'      => $email,
                'first_name' => $data['first_name'] ?? $data['voornaam'] ?? '',
                'last_name'  => $data['last_name']  ?? $data['achternaam'] ?? '',
                'phone'      => $data['phone']      ?? $data['telefoon'] ?? '',
                'address_line_1' => $data['address_line_1'] ?? $data['adres'] ?? '',
                'postal_code'    => $data['postal_code']    ?? $data['postcode'] ?? '',
                'city'           => $data['city']           ?? $data['plaats'] ?? '',
                'country'        => $data['country']        ?? '',
                'status'         => 'subscribed',
                'source'         => 'PSFID ' . $bron . ' (' . $form_id . ')',
            ];

            // Lege keys verwijderen
            $payload = array_filter( $payload, fn( $v ) => $v !== '' && $v !== null );

            // Custom field met token (instelbare slug, default psfid_token)
            $payload['custom_values'] = [ $slug => $token ];

            $contactApi = FluentCrmApi( 'contacts' );
            $contact    = $contactApi->getContact( $email );

            if ( $contact ) {
                $contact->fill( $payload )->save();
                if ( method_exists( $contact, 'updateCustomFieldValues' ) ) {
                    $contact->updateCustomFieldValues( [ $slug => $token ] );
                }
            } else {
                $contactApi->createOrUpdate( $payload );
            }

            // Markeer als gesynced
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'psfid_tokens',
                [ 'crm_synced' => 1 ],
                [ 'id' => $token_id ],
                [ '%d' ],
                [ '%d' ]
            );

            PSFID_Log::schrijf( $token_id, 'crm_synced', 'FluentCRM: ' . $email );

        } catch ( \Throwable $e ) {
            PSFID_Log::schrijf( $token_id, 'crm_error', 'FluentCRM: ' . $e->getMessage() );
        }
    }

    /**
     * De slug van het custom field waarin de token wordt weggeschreven.
     * Instelbaar via settings, default psfid_token.
     */
    public static function token_field_slug(): string {
        $s    = PSFID_Plugin::get_settings();
        $slug = sanitize_key( (string) ( $s['fc_token_field_slug'] ?? 'psfid_token' ) );
        return $slug !== '' ? $slug : 'psfid_token';
    }

    private function extract_email( array $data ): ?string {
        foreach ( [ 'email', 'email_address', 'mail' ] as $k ) {
            if ( ! empty( $data[ $k ] ) && is_email( $data[ $k ] ) ) {
                return sanitize_email( $data[ $k ] );
            }
        }
        return null;
    }

    /**
     * Zorg dat het token custom field bestaat in FluentCRM. Wordt aangeroepen
     * vanuit settings save. Geeft true terug als het veld bestaat of is aangemaakt,
     * false als auto-aanmaak niet lukte (dan moet de gebruiker het zelf aanmaken).
     *
     * De API om global custom fields te beheren verschilt per FluentCRM-versie.
     * We proberen daarom meerdere bekende paden, defensief en non-fataal.
     *
     * @param string|null $slug De slug om aan te maken. Null = uit settings halen.
     */
    public static function zorg_custom_field_bestaat( ?string $slug = null ): bool {
        if ( ! function_exists( 'FluentCrmApi' ) ) {
            return false;
        }

        $slug  = $slug !== null ? sanitize_key( $slug ) : self::token_field_slug();
        $label = 'PSFID Token';

        try {
            // Pad 1: officiële CustomContactField service (FluentCRM 2.6+)
            $service_class = '\FluentCrm\App\Services\CustomContactField';
            if ( class_exists( $service_class ) ) {
                $service = new $service_class();

                $bestaande = method_exists( $service, 'getGlobalFields' )
                    ? (array) $service->getGlobalFields()
                    : [];
                $fields = $bestaande['fields'] ?? $bestaande;

                if ( self::slug_in_fields( $slug, (array) $fields ) ) {
                    return true; // bestaat al
                }

                $fields[] = [
                    'type'    => 'text',
                    'label'   => $label,
                    'slug'    => $slug,
                ];

                if ( method_exists( $service, 'saveGlobalFields' ) ) {
                    $service->saveGlobalFields( [ 'fields' => $fields ] );
                    return true;
                }
                if ( method_exists( $service, 'addGlobalField' ) ) {
                    $service->addGlobalField( [ 'type' => 'text', 'label' => $label, 'slug' => $slug ] );
                    return true;
                }
            }

            // Pad 2: option-based fallback (oudere versies bewaren fields in een option)
            $option_keys = [ '_fluentcrm_contact_custom_fields', 'fluentcrm_contact_custom_fields' ];
            foreach ( $option_keys as $opt ) {
                $stored = get_option( $opt, null );
                if ( is_array( $stored ) ) {
                    if ( self::slug_in_fields( $slug, $stored ) ) {
                        return true;
                    }
                    $stored[] = [ 'type' => 'text', 'label' => $label, 'slug' => $slug ];
                    update_option( $opt, $stored );
                    return true;
                }
            }

            // Geen bekend pad beschikbaar: niet fataal, gebruiker maakt veld zelf aan.
            PSFID_Log::schrijf( null, 'fc_custom_field_manual', 'Kon custom field "' . $slug . '" niet automatisch aanmaken. Maak het handmatig aan in FluentCRM.' );
            return false;

        } catch ( \Throwable $e ) {
            PSFID_Log::schrijf( null, 'error', 'FluentCRM custom field create: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Helper: zit een slug al in een fields-array (ongeacht structuur-variant)?
     */
    private static function slug_in_fields( string $slug, array $fields ): bool {
        foreach ( $fields as $field ) {
            $f_slug = is_array( $field ) ? ( $field['slug'] ?? '' ) : ( is_object( $field ) ? ( $field->slug ?? '' ) : '' );
            if ( $f_slug === $slug ) {
                return true;
            }
        }
        return false;
    }
}
