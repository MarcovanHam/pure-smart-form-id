<?php
/**
 * Fluent Forms integratie:
 * - Capture: bij submit data + email/phone opslaan, token genereren of bestaande gebruiken
 * - Pre-fill: smartcodes voor bekende velden
 * - Auto-inject: hidden token veld
 */

defined( 'ABSPATH' ) || exit;

class PSFID_FluentForms {

    public function __construct() {
        // Capture submission
        add_action( 'fluentform/before_submission_confirmation', [ $this, 'capture_submission' ], 10, 3 );

        // Smartcode {psfid.voornaam} voor pre-fill
        add_filter( 'fluentform/smartcode_group_psfid', [ $this, 'smartcode_callback' ], 10, 4 );
        add_filter( 'fluentform/editor_smartcode_group_psfid', [ $this, 'register_smartcodes' ] );

        // Inject token in confirmation response zodat JS het kan oppakken
        add_filter( 'fluentform/submission_confirmation', [ $this, 'inject_token_in_response' ], 10, 3 );

        // Inject hidden field via filter (alleen als auto-inject aan staat)
        $instellingen = PSFID_Plugin::get_settings();
        if ( ! empty( $instellingen['auto_inject_token'] ) ) {
            add_filter( 'fluentform/rendering_form', [ $this, 'inject_token_field' ], 10, 1 );
        }

        // Save & Resume draft pre-fill via filter op rendering form
        add_filter( 'fluentform/rendering_form', [ $this, 'prefill_from_draft' ], 20, 1 );
    }

    /**
     * Save & Resume draft pre-fill voor Fluent Forms.
     *
     * Werkt met de FF "Save and Resume" feature (paid addon). Bij retour met
     * een PSFID token wordt de partial state uit de FF draft tabel gelezen
     * en gebruikt om de velden vooraf in te vullen.
     */
    public function prefill_from_draft( $form ) {
        $token = PSFID_Resolver::get_current();
        if ( ! $token || empty( $token->email ) ) {
            return $form;
        }

        $form_id = (int) ( $form->id ?? 0 );
        if ( $form_id <= 0 ) {
            return $form;
        }

        $partial = $this->haal_draft_op( $form_id, (string) $token->email );
        if ( empty( $partial ) || ! is_array( $partial ) ) {
            return $form;
        }

        // FF formulieren: $form->fields['fields'] is een array van veld-definities.
        if ( ! isset( $form->fields['fields'] ) || ! is_array( $form->fields['fields'] ) ) {
            return $form;
        }

        foreach ( $form->fields['fields'] as &$field ) {
            $name = $field['attributes']['name'] ?? '';
            if ( ! $name ) {
                continue;
            }

            // Composite velden zoals names[first_name]
            if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
                foreach ( $field['fields'] as $sub_key => &$sub ) {
                    $sub_name = $sub['attributes']['name'] ?? '';
                    // Strip names[...] of address[...] prefix
                    if ( preg_match( '/\[(.+?)\]/', $sub_name, $m ) ) {
                        $sub_name = $m[1];
                    }
                    if ( $sub_name && isset( $partial[ $sub_name ] ) && $partial[ $sub_name ] !== '' ) {
                        $sub['attributes']['value'] = (string) $partial[ $sub_name ];
                    }
                    // Ook check parent[child] format
                    $full_key = $field['attributes']['name'] . '[' . $sub_key . ']';
                    if ( isset( $partial[ $full_key ] ) && $partial[ $full_key ] !== '' ) {
                        $sub['attributes']['value'] = (string) $partial[ $full_key ];
                    }
                }
                unset( $sub );
                continue;
            }

            // Reguliere velden
            if ( isset( $partial[ $name ] ) && $partial[ $name ] !== '' ) {
                $val = $partial[ $name ];
                if ( is_array( $val ) ) {
                    $val = wp_json_encode( $val );
                }
                $field['attributes']['value'] = (string) $val;
            }
        }
        unset( $field );

        return $form;
    }

    /**
     * Haal de meest recente FF Save & Resume draft op.
     */
    private function haal_draft_op( int $form_id, string $email ): ?array {
        global $wpdb;

        // FF Pro Save & Resume tabel naam kan verschillen per versie
        $kandidaten = [
            $wpdb->prefix . 'fluentform_draft_submissions',
            $wpdb->prefix . 'ff_draft_submissions',
        ];

        $table = null;
        foreach ( $kandidaten as $kandidaat ) {
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $kandidaat ) ) === $kandidaat ) {
                $table = $kandidaat;
                break;
            }
        }

        if ( ! $table ) {
            return null;
        }

        // FF tabel heeft meestal: id, form_id, user_email (of email), response, created_at
        // We proberen beide kolomnamen voor email
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $table
             WHERE form_id = %d
               AND ( user_email = %s OR email = %s )
             ORDER BY created_at DESC LIMIT 1",
            $form_id,
            $email,
            $email
        ) );

        if ( ! $row ) {
            return null;
        }

        // FF slaat de response meestal op als JSON in 'response' of 'submission' kolom
        $raw = $row->response ?? $row->submission ?? '';
        if ( ! $raw ) {
            return null;
        }

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            return null;
        }

        return $data;
    }

    /**
     * Capture: data extraheren en opslaan.
     */
    public function capture_submission( $insertId, $formData, $form ) {
        try {
            $platte_data = $this->plat_maken( $formData );

            // Token resolution chain (URL, cookie, email, phone, of nieuw)
            $token_record = PSFID_Token::resolve( $platte_data );

            if ( ! $token_record ) {
                $email = $this->extract_email( $platte_data );
                $phone = $this->extract_phone( $platte_data );
                $token_id = PSFID_Token::maak_record( $email, $phone, 'fluentforms_' . $form->id );
                $token_record = (object) [
                    'id'    => $token_id,
                    'token' => $this->haal_token_string( $token_id ),
                ];
                PSFID_Log::schrijf( $token_id, 'capture_new', 'Form ' . $form->id );
            } else {
                // Bestaand record updaten met evt. nieuwe email/phone
                $email = $this->extract_email( $platte_data );
                $phone = $this->extract_phone( $platte_data );
                PSFID_Token::raak_aan( (int) $token_record->id, $email, $phone );
                PSFID_Log::schrijf( (int) $token_record->id, 'capture_update', 'Form ' . $form->id );
            }

            // Velden opslaan
            PSFID_Store::bewaar_velden( (int) $token_record->id, $platte_data );

            // Cookie zetten
            PSFID_Cookie::zet( $token_record->token );

            // Token meeschrijven in submission record (voor admin overzicht)
            if ( function_exists( 'wpFluent' ) ) {
                wpFluent()->table( 'fluentform_submission_meta' )->insert( [
                    'form_id'        => $form->id,
                    'response_id'    => $insertId,
                    'meta_key'       => '_psfid_token',
                    'value'          => $token_record->token,
                    'name'           => 'PSFID Token',
                    'created_at'     => current_time( 'mysql' ),
                    'updated_at'     => current_time( 'mysql' ),
                ] );
            }

            // Trigger CRM sync
            do_action( 'psfid/token_captured', (int) $token_record->id, $token_record->token, $platte_data, 'fluentforms', $form->id );

        } catch ( \Throwable $e ) {
            PSFID_Log::schrijf( null, 'error', 'FluentForms capture: ' . $e->getMessage() );
        }
    }

    /**
     * Smartcode callback: {psfid.voornaam}, {psfid.email} etc.
     */
    public function smartcode_callback( $value, $key, $context, $form ) {
        $val = PSFID_Resolver::get_field( strtolower( $key ) );
        return $val !== null ? $val : $value;
    }

    /**
     * Beschikbare smartcodes laten zien in form editor.
     */
    public function register_smartcodes( $smartcodes ) {
        $velden = [
            'voornaam'        => 'Voornaam',
            'achternaam'      => 'Achternaam',
            'first_name'      => 'First Name',
            'last_name'       => 'Last Name',
            'email'           => 'Email',
            'phone'           => 'Telefoon',
            'address_line_1'  => 'Adres',
            'postal_code'     => 'Postcode',
            'city'            => 'Plaats',
            'country'         => 'Land',
            'token'           => 'Token (de code zelf)',
        ];

        $shortcodes = [];
        foreach ( $velden as $key => $label ) {
            $shortcodes[ '{psfid.' . $key . '}' ] = $label;
        }

        return [
            'key'        => 'psfid',
            'title'      => 'Pure Smart Form ID',
            'shortcodes' => $shortcodes,
        ];
    }

    /**
     * Token meegeven in confirmation response zodat de cookie via JS gezet kan worden.
     */
    public function inject_token_in_response( $confirmation, $form, $insertId ) {
        // Haal de token op die we zojuist gekoppeld hebben
        if ( function_exists( 'wpFluent' ) ) {
            $meta = wpFluent()->table( 'fluentform_submission_meta' )
                ->where( 'response_id', $insertId )
                ->where( 'meta_key', '_psfid_token' )
                ->first();
            if ( $meta && ! empty( $meta->value ) ) {
                if ( is_array( $confirmation ) ) {
                    $confirmation['psfid_token'] = $meta->value;
                }
            }
        }
        return $confirmation;
    }

    /**
     * Auto-inject hidden token field. Dit werkt door het rendering filter
     * te gebruiken om de smartcode {psfid.token} te laten resolven en als
     * hidden field beschikbaar te maken in de form mapping.
     *
     * Praktisch: we voegen een unieke shortcode toe aan de form-fields,
     * en gebruikers koppelen dat zelf in CRM. Default field name komt uit settings.
     *
     * Voor échte auto-injectie zonder dat user iets hoeft te doen, gebruiken we
     * een Render hook om HTML toe te voegen.
     */
    public function inject_token_field( $form ) {
        // Voeg hidden field toe aan form fields array zodat het bij submit bekend is
        // Werkt door fields_array vóór rendering aan te passen
        $instellingen = PSFID_Plugin::get_settings();
        $field_name   = $instellingen['token_field_name'] ?? 'form_token';

        if ( ! isset( $form->fields ) || ! is_array( $form->fields ) ) {
            return $form;
        }

        // Check of er al een veld met deze naam bestaat
        $heeft_veld = false;
        if ( isset( $form->fields['fields'] ) && is_array( $form->fields['fields'] ) ) {
            foreach ( $form->fields['fields'] as $field ) {
                if ( isset( $field['attributes']['name'] ) && $field['attributes']['name'] === $field_name ) {
                    $heeft_veld = true;
                    break;
                }
            }
        }

        if ( ! $heeft_veld && isset( $form->fields['fields'] ) ) {
            $token = PSFID_Resolver::get_current();
            $token_value = $token ? $token->token : '';

            $hidden_field = [
                'index'      => count( $form->fields['fields'] ),
                'element'    => 'input_hidden',
                'attributes' => [
                    'type'  => 'hidden',
                    'name'  => $field_name,
                    'value' => $token_value,
                ],
                'settings'   => [
                    'label'           => 'PSFID Token',
                    'admin_field_label' => 'PSFID Token',
                    'container_class' => 'psfid-hidden',
                ],
                'editor_options' => [
                    'title' => 'PSFID Token',
                    'icon_class' => 'ff-edit-hidden',
                ],
                'uniqElKey' => 'psfid_' . wp_generate_uuid4(),
            ];

            $form->fields['fields'][] = $hidden_field;
        }

        return $form;
    }

    /* ---------- Helpers ---------- */

    private function plat_maken( array $formData ): array {
        $result = [];
        foreach ( $formData as $key => $value ) {
            // names[first_name] => first_name
            if ( is_array( $value ) ) {
                foreach ( $value as $sub_key => $sub_val ) {
                    if ( is_string( $sub_val ) || is_numeric( $sub_val ) ) {
                        $result[ strtolower( (string) $sub_key ) ] = (string) $sub_val;
                    }
                }
            } elseif ( is_string( $value ) || is_numeric( $value ) ) {
                $result[ strtolower( (string) $key ) ] = (string) $value;
            }
        }
        return $result;
    }

    private function extract_email( array $data ): ?string {
        foreach ( [ 'email', 'email_address', 'mail', 'emailadres' ] as $k ) {
            if ( ! empty( $data[ $k ] ) && is_email( $data[ $k ] ) ) {
                return sanitize_email( $data[ $k ] );
            }
        }
        foreach ( $data as $v ) {
            if ( is_string( $v ) && is_email( $v ) ) {
                return sanitize_email( $v );
            }
        }
        return null;
    }

    private function extract_phone( array $data ): ?string {
        foreach ( [ 'phone', 'telefoon', 'tel', 'mobiel' ] as $k ) {
            if ( ! empty( $data[ $k ] ) ) {
                return PSFID_Token::normaliseer_phone( (string) $data[ $k ] );
            }
        }
        return null;
    }

    private function haal_token_string( int $token_id ): string {
        global $wpdb;
        return (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT token FROM {$wpdb->prefix}psfid_tokens WHERE id = %d",
            $token_id
        ) );
    }
}
