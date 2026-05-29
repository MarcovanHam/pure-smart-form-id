<?php
/**
 * Gravity Forms integratie:
 * - Capture: bij submit data + email/phone opslaan
 * - Pre-fill: gform_field_value_<naam> filter voor alle bekende keys
 * - Auto-inject: hidden field via gform_pre_render
 * - Merge tag: {psfid_token} en {psfid:voornaam}
 */

defined( 'ABSPATH' ) || exit;

class PSFID_GravityForms {

    public function __construct() {
        // Capture na submission
        add_action( 'gform_after_submission', [ $this, 'capture_submission' ], 10, 2 );

        // Confirmation: token meegeven aan front-end
        add_filter( 'gform_confirmation', [ $this, 'inject_token_in_confirmation' ], 10, 4 );

        // Pre-fill: dynamic gform_field_value_<param> filters voor alle bekende velden
        add_action( 'gform_pre_render', [ $this, 'register_prefill_filters' ] );

        // Save & Continue draft pre-fill: vul velden uit een eerder opgeslagen GF draft
        // Heeft hogere prioriteit dan register_prefill_filters zodat het de actuele state pakt
        add_filter( 'gform_pre_render', [ $this, 'prefill_from_draft' ], 30 );

        // Auto-resume: bij PSFID token, redirect naar gform_resume_token URL zodat GF
        // op de juiste pagina van een multi-page form opent (server-side detectie)
        add_action( 'template_redirect', [ $this, 'maybe_redirect_to_resume' ], 5 );

        // Auto-resume robuuster: tijdens form-render direct een JS-redirect injecteren
        // als er een draft is. Werkt voor alle embed-types (shortcode, block, Elementor,
        // Divi, custom theme). Triggert op elke GF render.
        add_filter( 'gform_pre_render', [ $this, 'inject_resume_script' ], 40 );

        // Merge tag {psfid:key}
        add_filter( 'gform_replace_merge_tags', [ $this, 'replace_merge_tags' ], 10, 7 );

        // Auto-inject hidden field
        $instellingen = PSFID_Plugin::get_settings();
        if ( ! empty( $instellingen['auto_inject_token'] ) ) {
            add_filter( 'gform_pre_render', [ $this, 'inject_token_field' ] );
            add_filter( 'gform_pre_validation', [ $this, 'inject_token_field' ] );
            add_filter( 'gform_admin_pre_render', [ $this, 'inject_token_field' ] );
            add_filter( 'gform_pre_submission_filter', [ $this, 'inject_token_field' ] );
        }
    }

    /**
     * Capture de submission data.
     */
    public function capture_submission( $entry, $form ): void {
        try {
            $platte_data = $this->entry_naar_platte_data( $entry, $form );

            // Token resolution chain
            $token_record = PSFID_Token::resolve( $platte_data );

            if ( ! $token_record ) {
                $email    = $this->extract_email( $platte_data );
                $phone    = $this->extract_phone( $platte_data );
                $token_id = PSFID_Token::maak_record( $email, $phone, 'gravity_' . $form['id'] );
                $token_record = (object) [
                    'id'    => $token_id,
                    'token' => $this->haal_token_string( $token_id ),
                ];
                PSFID_Log::schrijf( $token_id, 'capture_new', 'GF Form ' . $form['id'] );
            } else {
                $email = $this->extract_email( $platte_data );
                $phone = $this->extract_phone( $platte_data );
                PSFID_Token::raak_aan( (int) $token_record->id, $email, $phone );
                PSFID_Log::schrijf( (int) $token_record->id, 'capture_update', 'GF Form ' . $form['id'] );
            }

            // Velden opslaan
            PSFID_Store::bewaar_velden( (int) $token_record->id, $platte_data );

            // Cookie zetten
            PSFID_Cookie::zet( $token_record->token );

            // Sla token op als entry meta voor admin overzicht
            if ( class_exists( 'GFAPI' ) ) {
                gform_update_meta( $entry['id'], '_psfid_token', $token_record->token );

                // Werk het hidden token-field in de entry zelf bij, zodat het
                // ook zichtbaar is in de standaard entries-kolom.
                $instellingen = PSFID_Plugin::get_settings();
                $token_field_name = $instellingen['token_field_name'] ?? 'form_token';

                foreach ( $form['fields'] as $f ) {
                    $f_name = is_array( $f ) ? ( $f['inputName'] ?? '' ) : ( $f->inputName ?? '' );
                    $f_id   = is_array( $f ) ? ( $f['id'] ?? 0 ) : ( $f->id ?? 0 );
                    if ( $f_name === $token_field_name && $f_id ) {
                        \GFAPI::update_entry_field( (int) $entry['id'], (string) $f_id, $token_record->token );
                        break;
                    }
                }
            }

            // Trigger CRM sync
            do_action( 'psfid/token_captured', (int) $token_record->id, $token_record->token, $platte_data, 'gravity', $form['id'] );

        } catch ( \Throwable $e ) {
            PSFID_Log::schrijf( null, 'error', 'GravityForms capture: ' . $e->getMessage() );
        }
    }

    /**
     * Token mee in confirmation, zodat JS hem kan oppakken.
     */
    public function inject_token_in_confirmation( $confirmation, $form, $entry, $ajax ) {
        $token = '';
        if ( ! empty( $entry['id'] ) ) {
            $token = (string) gform_get_meta( $entry['id'], '_psfid_token' );
        }
        if ( ! $token ) {
            return $confirmation;
        }

        // Voeg een dispatch script toe zodat psfid.js het oppakt
        $velden = [];
        $token_id = $this->haal_token_id( $token );
        if ( $token_id ) {
            $velden = PSFID_Store::haal_velden_op( $token_id );
        }

        $script = '<script>document.dispatchEvent(new CustomEvent("gform_confirmation_loaded", {detail: ' .
                  wp_json_encode( [ 'token' => $token, 'fields' => $velden ] ) . '}));</script>';

        if ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
            // Redirect-confirmation: token als URL parameter toevoegen
            $instellingen = PSFID_Plugin::get_settings();
            $param        = $instellingen['url_param'] ?? 'id';
            $confirmation['redirect'] = add_query_arg( $param, $token, $confirmation['redirect'] );
        } else {
            $confirmation = ( is_array( $confirmation ) ? '' : $confirmation ) . $script;
        }

        return $confirmation;
    }

    /**
     * Registreer voor elk veld in dit formulier een pre-fill default value.
     * Werkt voor gewone velden (via inputName of label) EN voor composite
     * velden zoals Name en Address (via sub-inputs labels).
     */
    public function register_prefill_filters( $form ) {
        $velden = PSFID_Resolver::get_current_fields();
        if ( empty( $velden ) ) {
            return $form;
        }

        if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
            return $form;
        }

        // Mapping van veld-labels (in NL/EN/DE/ES) naar standaard data keys
        $label_map = [
            // Name field sub-labels
            'first'        => 'first_name',
            'voornaam'     => 'first_name',
            'vorname'      => 'first_name',
            'nombre'       => 'first_name',
            'last'         => 'last_name',
            'achternaam'   => 'last_name',
            'nachname'     => 'last_name',
            'apellido'     => 'last_name',
            'apellidos'    => 'last_name',
            'middle'       => 'middle_name',
            'prefix'       => 'prefix',
            'suffix'       => 'suffix',
            // Address field sub-labels
            'street_address'  => 'address_line_1',
            'straße'          => 'address_line_1',
            'strasse'         => 'address_line_1',
            'adres'           => 'address_line_1',
            'direccion'       => 'address_line_1',
            'dirección'       => 'address_line_1',
            'address_line_2'  => 'address_line_2',
            'city'            => 'city',
            'plaats'          => 'city',
            'woonplaats'      => 'city',
            'stadt'           => 'city',
            'ciudad'          => 'city',
            'state_province'  => 'state',
            'state'           => 'state',
            'provincie'       => 'state',
            'provincia'       => 'state',
            'zip'             => 'postal_code',
            'zip_postal_code' => 'postal_code',
            'postal_code'     => 'postal_code',
            'postcode'        => 'postal_code',
            'plz'             => 'postal_code',
            'codigo_postal'   => 'postal_code',
            'código_postal'   => 'postal_code',
            'country'         => 'country',
            'land'            => 'country',
            'pais'            => 'country',
            'país'            => 'country',
            // Common standalone fields
            'email'           => 'email',
            'e-mail'          => 'email',
            'mailadres'       => 'email',
            'phone'           => 'phone',
            'telefoon'        => 'phone',
            'telefon'         => 'phone',
            'telefono'        => 'phone',
            'teléfono'        => 'phone',
            'company'         => 'company',
            'bedrijf'         => 'company',
            'firma'           => 'company',
            'empresa'         => 'company',
        ];

        foreach ( $form['fields'] as &$field ) {
            $field_type = $field->type ?? '';

            // Composite velden: Name, Address
            if ( in_array( $field_type, [ 'name', 'address' ], true ) && is_array( $field->inputs ?? null ) ) {
                foreach ( $field->inputs as $idx => $sub ) {
                    $sub_label = strtolower( str_replace( ' ', '_', (string) ( $sub['label'] ?? '' ) ) );
                    $key       = $label_map[ $sub_label ] ?? $sub_label;

                    if ( $key && isset( $velden[ $key ] ) && ! empty( $velden[ $key ] ) ) {
                        // GF prefill werkt via $inputs[$idx]['defaultValue']
                        $field->inputs[ $idx ]['defaultValue'] = $velden[ $key ];
                    }
                }
                continue;
            }

            // Reguliere velden: probeer inputName en label
            $candidates = [];
            if ( ! empty( $field->inputName ) ) {
                $candidates[] = strtolower( (string) $field->inputName );
            }
            if ( ! empty( $field->label ) ) {
                $candidates[] = strtolower( str_replace( ' ', '_', (string) $field->label ) );
            }

            foreach ( $candidates as $cand ) {
                $key = $label_map[ $cand ] ?? $cand;
                if ( isset( $velden[ $key ] ) && ! empty( $velden[ $key ] ) ) {
                    $field->defaultValue = $velden[ $key ];
                    break;
                }
            }
        }
        unset( $field );

        return $form;
    }

    /**
     * Inject resume script tijdens form render.
     *
     * Tijdens gform_pre_render weten we 100% zeker welk form gerenderd wordt
     * (ongeacht embed-methode: shortcode, Gutenberg block, Elementor widget,
     * Divi module, custom theme template). Bij hit injecteren we een script
     * dat direct redirect met gform_resume_token zodat GF zelf naar de juiste
     * pagina springt.
     *
     * Loopt op prio 40 (na pre-fill), zodat als de redirect plaatsvindt de
     * pre-fill al is gedaan voor het geval de redirect ergens faalt.
     */
    public function inject_resume_script( $form ) {
        // Skip in admin / preview / al-resume-bezig
        if ( is_admin() ) {
            return $form;
        }
        if ( ! empty( $_GET['gform_resume_token'] ) ) {
            return $form;
        }

        $token = PSFID_Resolver::get_current();
        if ( ! $token || empty( $token->email ) ) {
            return $form;
        }

        $form_id = (int) ( $form['id'] ?? 0 );
        if ( $form_id <= 0 ) {
            return $form;
        }

        // Check of we al in resume-flow zitten (beide parameter-namen)
        if ( ! empty( $_GET['gf_token'] ) || ! empty( $_GET['gform_resume_token'] ) ) {
            return $form;
        }

        $draft = $this->haal_draft_record_op( $form_id, (string) $token->email );

        // Audit log voor debug
        PSFID_Log::schrijf(
            (int) $token->id,
            'gf_resume_check',
            'Form ' . $form_id . ' email ' . $token->email . ' → ' . ( $draft && ! empty( $draft->uuid ) ? 'FOUND uuid=' . substr( $draft->uuid, 0, 12 ) : 'NOT FOUND' )
        );

        if ( ! $draft || empty( $draft->uuid ) ) {
            return $form;
        }

        // Output redirect script via wp_footer
        // Gebruik gf_token (modern, GF 2.5+) als primary + gform_resume_token (legacy) als fallback
        $uuid = $draft->uuid;
        add_action( 'wp_footer', function () use ( $uuid, $form_id ) {
            $url = add_query_arg( [
                'gf_token'            => $uuid,
                'gform_resume_token'  => $uuid, // legacy fallback voor oudere GF versies
            ] );
            $guard_key = 'psfid_resumed_' . $form_id . '_' . substr( $uuid, 0, 8 );
            ?>
            <script>
            (function () {
                // Skip als al een resume-parameter in URL (beide namen checken)
                var qs = window.location.search;
                if (qs.indexOf('gf_token=') !== -1 || qs.indexOf('gform_resume_token=') !== -1) return;
                try {
                    if (sessionStorage.getItem(<?php echo wp_json_encode( $guard_key ); ?>)) return;
                    sessionStorage.setItem(<?php echo wp_json_encode( $guard_key ); ?>, '1');
                } catch (e) { /* sessionStorage not available */ }
                console.log('[PSFID] Resuming GF form <?php echo (int) $form_id; ?> via draft <?php echo esc_js( substr( $uuid, 0, 12 ) ); ?> (gf_token + gform_resume_token)');
                window.location.replace(<?php echo wp_json_encode( $url ); ?>);
            })();
            </script>
            <?php
        }, 1 );

        return $form;
    }

    /**
     * Auto-resume: bij PSFID-token, kijk of de bezoeker een GF draft heeft voor een
     * formulier op deze pagina. Zo ja, redirect met gform_resume_token zodat GF
     * direct naar de juiste pagina springt.
     */
    public function maybe_redirect_to_resume(): void {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        // Niet redirecten als we al een resume-token in URL hebben (beide parameter-namen)
        if ( ! empty( $_GET['gf_token'] ) || ! empty( $_GET['gform_resume_token'] ) ) {
            return;
        }

        // PSFID token nodig
        $token = PSFID_Resolver::get_current();
        if ( ! $token || empty( $token->email ) ) {
            return;
        }

        // Detecteer GF form-IDs op deze pagina
        $form_ids = $this->detecteer_form_ids_op_pagina();
        if ( empty( $form_ids ) ) {
            return;
        }

        // Voor elk form-ID checken of er een draft is
        foreach ( $form_ids as $form_id ) {
            $draft = $this->haal_draft_record_op( (int) $form_id, (string) $token->email );
            if ( $draft && ! empty( $draft->uuid ) ) {
                // Bouw redirect URL met beide parameter-namen voor compatibiliteit
                $redirect_url = add_query_arg( [
                    'gf_token'           => $draft->uuid,
                    'gform_resume_token' => $draft->uuid,
                ] );
                PSFID_Log::schrijf( (int) $token->id, 'gf_resume_redirect', 'Form ' . $form_id . ' uuid=' . substr( $draft->uuid, 0, 12 ) . ' (gf_token + gform_resume_token)' );
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
    }

    /**
     * Detecteer welke GF form-IDs op de huidige pagina staan.
     * Scant post content op [gravityform] / [gravityforms] shortcodes en
     * Gutenberg blocks. Voor dynamisch ingebrachte formulieren is er een
     * JS-fallback in psfid.js.
     */
    private function detecteer_form_ids_op_pagina(): array {
        global $post;
        if ( ! $post || empty( $post->post_content ) ) {
            return [];
        }

        $ids = [];

        // Shortcode [gravityform id="X"] of [gravityforms id="X"]
        if ( preg_match_all( '/\[gravityform[s]?\s+[^\]]*id\s*=\s*[\"\']?(\d+)/i', $post->post_content, $matches ) ) {
            foreach ( $matches[1] as $id ) {
                $ids[] = (int) $id;
            }
        }

        // Gutenberg block: <!-- wp:gravityforms/form ... "formId":"X" ... -->
        if ( preg_match_all( '/wp:gravityforms\/form[^>]*"formId"\s*:\s*"?(\d+)/', $post->post_content, $matches ) ) {
            foreach ( $matches[1] as $id ) {
                $ids[] = (int) $id;
            }
        }

        return array_unique( array_filter( $ids ) );
    }

    /**
     * Haal de meest recente GF draft op (volledig record, niet alleen submission).
     * Email match is case-insensitive zodat "Marco@x.nl" en "marco@x.nl" beide matchen.
     */
    private function haal_draft_record_op( int $form_id, string $email ): ?object {
        global $wpdb;

        $table = $wpdb->prefix . 'gf_draft_submissions';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return null;
        }

        $email_lower = strtolower( trim( $email ) );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT uuid, submission, date_created FROM $table
             WHERE form_id = %d AND LOWER(email) = %s
             ORDER BY date_created DESC LIMIT 1",
            $form_id,
            $email_lower
        ) );
    }

    /**
     * Save & Continue draft pre-fill.
     *
     * Bij elke form-render: kijk of de huidige bezoeker (PSFID token bekend, met email)
     * een Save & Continue draft heeft voor dit formulier. Zo ja, gebruik die draft-data
     * om de velden vooraf in te vullen.
     *
     * Reden: bij Save & Continue triggert gform_after_submission NIET, dus PSFID heeft
     * die data niet. Door direct de GF draft-tabel te lezen krijgt de bezoeker zijn
     * partial state weer terug bij retour via een PSFID-link.
     */
    public function prefill_from_draft( $form ) {
        $token = PSFID_Resolver::get_current();
        if ( ! $token || empty( $token->email ) ) {
            return $form;
        }

        $form_id = (int) ( $form['id'] ?? 0 );
        if ( $form_id <= 0 ) {
            return $form;
        }

        $partial = $this->haal_draft_op( $form_id, (string) $token->email );
        if ( ! $partial ) {
            return $form;
        }

        // $partial is een array met field-IDs als keys (1, 2, 3.1, 3.2, etc).
        // Toepassen op velden in het formulier.
        if ( ! empty( $form['fields'] ) && is_array( $form['fields'] ) ) {
            foreach ( $form['fields'] as &$field ) {
                $field_type = $field->type ?? '';

                // Composite velden (Name, Address): vul sub-inputs vanaf hun ID
                if ( in_array( $field_type, [ 'name', 'address' ], true ) && is_array( $field->inputs ?? null ) ) {
                    foreach ( $field->inputs as $idx => $sub ) {
                        $sub_id = (string) ( $sub['id'] ?? '' );
                        if ( $sub_id && isset( $partial[ $sub_id ] ) && $partial[ $sub_id ] !== '' ) {
                            $field->inputs[ $idx ]['defaultValue'] = $partial[ $sub_id ];
                        }
                    }
                    continue;
                }

                // Reguliere velden
                $field_id = (string) ( $field->id ?? '' );
                if ( $field_id && isset( $partial[ $field_id ] ) && $partial[ $field_id ] !== '' ) {
                    $val = $partial[ $field_id ];
                    if ( is_array( $val ) ) {
                        $val = wp_json_encode( $val );
                    }
                    $field->defaultValue = (string) $val;
                }
            }
            unset( $field );
        }

        return $form;
    }

    /**
     * Haal de meest recente GF Save & Continue draft op voor dit form-ID + email.
     * Geeft de partial entry terug (associatieve array met field-IDs als keys).
     */
    private function haal_draft_op( int $form_id, string $email ): ?array {
        global $wpdb;

        // GF tabel: wp_gf_draft_submissions (uuid, email, form_id, date_created, ip, source_url, submission, submission_filter)
        $table = $wpdb->prefix . 'gf_draft_submissions';

        // Tabel bestaat?
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return null;
        }

        $email_lower = strtolower( trim( $email ) );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT submission FROM $table
             WHERE form_id = %d AND LOWER(email) = %s
             ORDER BY date_created DESC LIMIT 1",
            $form_id,
            $email_lower
        ) );

        if ( ! $row || empty( $row->submission ) ) {
            return null;
        }

        // GF serialiseert de submission. Sinds GF 2.x is het een JSON-encoded structuur.
        $data = json_decode( $row->submission, true );
        if ( ! is_array( $data ) ) {
            // Fallback voor oudere GF versies (PHP serialized)
            $data = @unserialize( $row->submission );
            if ( ! is_array( $data ) ) {
                return null;
            }
        }

        // De partial entry zit onder verschillende mogelijke keys afhankelijk van GF versie:
        // - $data['partial_entry'] (modern)
        // - $data['submission'] (alternatief)
        // - direct als field => value array (oud)
        if ( isset( $data['partial_entry'] ) && is_array( $data['partial_entry'] ) ) {
            return $data['partial_entry'];
        }
        if ( isset( $data['submission'] ) && is_array( $data['submission'] ) ) {
            return $data['submission'];
        }

        // Probeer als de array zelf field-IDs als keys heeft (numeriek of "X.Y")
        $first_key = array_key_first( $data );
        if ( $first_key !== null && ( is_numeric( $first_key ) || preg_match( '/^\d+\.\d+$/', (string) $first_key ) ) ) {
            return $data;
        }

        return null;
    }

    /**
     * Merge tag {psfid:voornaam} en {psfid_token}.
     */
    public function replace_merge_tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
        // {psfid_token}
        if ( strpos( $text, '{psfid_token}' ) !== false ) {
            $token = PSFID_Resolver::get_current();
            $val   = $token ? $token->token : '';
            $text  = str_replace( '{psfid_token}', $esc_html ? esc_html( $val ) : $val, $text );
        }

        // {psfid:key}
        if ( preg_match_all( '/\{psfid:([a-z0-9_-]+)\}/i', $text, $matches ) ) {
            foreach ( $matches[1] as $i => $key ) {
                $val  = (string) ( PSFID_Resolver::get_field( strtolower( $key ) ) ?? '' );
                $val  = $esc_html ? esc_html( $val ) : $val;
                $text = str_replace( $matches[0][ $i ], $val, $text );
            }
        }

        return $text;
    }

    /**
     * Inject hidden field met token-veld-naam in elk formulier.
     */
    public function inject_token_field( $form ) {
        if ( ! is_array( $form ) || empty( $form['fields'] ) ) {
            return $form;
        }

        $instellingen = PSFID_Plugin::get_settings();
        $field_name   = $instellingen['token_field_name'] ?? 'form_token';

        // Check of veld al bestaat
        foreach ( $form['fields'] as $f ) {
            $f_name = is_array( $f ) ? ( $f['inputName'] ?? '' ) : ( $f->inputName ?? '' );
            if ( $f_name === $field_name ) {
                return $form;
            }
        }

        // Token waarde
        $token = PSFID_Resolver::get_current();
        $token_value = $token ? $token->token : '';

        // Maak een hidden field aan
        if ( ! class_exists( 'GF_Field_Hidden' ) && class_exists( 'GFCommon' ) ) {
            require_once GFCommon::get_base_path() . '/includes/fields/class-gf-field-hidden.php';
        }

        if ( class_exists( 'GF_Field_Hidden' ) ) {
            $hidden = new GF_Field_Hidden( [
                'id'                => $this->generate_field_id( $form ),
                'type'              => 'hidden',
                'label'             => 'PSFID Token',
                'inputName'         => $field_name,
                'allowsPrepopulate' => true,
                'defaultValue'      => $token_value,
                'cssClass'          => 'psfid-hidden',
            ] );
            $form['fields'][] = $hidden;
        }

        return $form;
    }

    /* ---------- Helpers ---------- */

    private function entry_naar_platte_data( array $entry, array $form ): array {
        $result = [];

        // Veld-type → standaard data key (voor GF field types).
        // Veel betrouwbaarder dan label-matching omdat het taal-onafhankelijk is.
        $type_to_key = [
            'email'   => 'email',
            'phone'   => 'phone',
            'website' => 'website',
        ];

        // Composite sub-labels mapping (NL/EN/DE/ES)
        $sub_label_map = [
            'first'           => 'first_name',
            'voornaam'        => 'first_name',
            'vorname'         => 'first_name',
            'nombre'          => 'first_name',
            'last'            => 'last_name',
            'achternaam'      => 'last_name',
            'nachname'        => 'last_name',
            'apellido'        => 'last_name',
            'apellidos'       => 'last_name',
            'middle'          => 'middle_name',
            'prefix'          => 'prefix',
            'suffix'          => 'suffix',
            'street_address'  => 'address_line_1',
            'straße'          => 'address_line_1',
            'strasse'         => 'address_line_1',
            'adres'           => 'address_line_1',
            'direccion'       => 'address_line_1',
            'address_line_2'  => 'address_line_2',
            'city'            => 'city',
            'plaats'          => 'city',
            'woonplaats'      => 'city',
            'stadt'           => 'city',
            'ciudad'          => 'city',
            'state_province'  => 'state',
            'state'           => 'state',
            'provincie'       => 'state',
            'provincia'       => 'state',
            'zip'             => 'postal_code',
            'zip_postal_code' => 'postal_code',
            'postal_code'     => 'postal_code',
            'postcode'        => 'postal_code',
            'plz'             => 'postal_code',
            'country'         => 'country',
            'land'            => 'country',
            'pais'            => 'country',
        ];

        // Aanvullende label-naar-key mapping voor NL/DE/ES labels op normale velden
        $label_map = [
            'email'           => 'email',
            'e-mail'          => 'email',
            'mailadres'       => 'email',
            'phone'           => 'phone',
            'telefoon'        => 'phone',
            'telefon'         => 'phone',
            'telefono'        => 'phone',
            'mobiel'          => 'phone',
            'mobiele_nummer'  => 'phone',
            'company'         => 'company',
            'bedrijf'         => 'company',
            'firma'           => 'company',
            'empresa'         => 'company',
        ];

        foreach ( $form['fields'] as $field ) {
            $field_id   = $field->id ?? null;
            $input_name = strtolower( (string) ( $field->inputName ?? '' ) );
            $field_type = $field->type ?? '';
            $field_label = strtolower( str_replace( ' ', '_', (string) ( $field->label ?? '' ) ) );

            if ( ! $field_id ) {
                continue;
            }

            // Composite fields (name, address)
            if ( in_array( $field_type, [ 'name', 'address' ], true ) && is_array( $field->inputs ?? null ) ) {
                foreach ( $field->inputs as $sub ) {
                    $sub_id    = (string) $sub['id'];
                    $sub_label = strtolower( str_replace( ' ', '_', (string) ( $sub['label'] ?? '' ) ) );
                    $value     = rgar( $entry, $sub_id );
                    $key = $sub_label_map[ $sub_label ] ?? $sub_label;

                    if ( $key && $value !== '' ) {
                        $result[ $key ] = $value;
                    }
                }
                continue;
            }

            // Reguliere velden
            $value = rgar( $entry, (string) $field_id );
            if ( $value === '' || $value === null ) {
                continue;
            }

            // Bepaal key in volgorde van betrouwbaarheid:
            // 1. Field type (email/phone/website) - taalonafhankelijk
            // 2. inputName als die expliciet is gezet
            // 3. Label gemapt via label_map (zoekt o.a. naar 'mobiel', 'telefoon' substring)
            // 4. Genormaliseerd label als fallback
            $key = '';

            if ( isset( $type_to_key[ $field_type ] ) ) {
                $key = $type_to_key[ $field_type ];
            }

            if ( ! $key && $input_name ) {
                $key = $input_name;
            }

            if ( ! $key && $field_label ) {
                // Direct in label_map?
                if ( isset( $label_map[ $field_label ] ) ) {
                    $key = $label_map[ $field_label ];
                } else {
                    // Substring match (bijv. "wat_is_jouw_mobiele_nummer" bevat "mobiel")
                    foreach ( $label_map as $needle => $mapped ) {
                        if ( strpos( $field_label, $needle ) !== false ) {
                            $key = $mapped;
                            break;
                        }
                    }
                    // Niets gevonden? Gebruik genormaliseerd label
                    if ( ! $key ) {
                        $key = $field_label;
                    }
                }
            }

            if ( $key ) {
                $result[ $key ] = is_array( $value ) ? wp_json_encode( $value ) : (string) $value;
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

    private function haal_token_id( string $token ): ?int {
        global $wpdb;
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}psfid_tokens WHERE token = %s",
            $token
        ) );
        return $id ? (int) $id : null;
    }

    private function generate_field_id( array $form ): int {
        $max_id = 0;
        foreach ( $form['fields'] as $f ) {
            $id = is_array( $f ) ? ( $f['id'] ?? 0 ) : ( $f->id ?? 0 );
            if ( $id > $max_id ) {
                $max_id = (int) $id;
            }
        }
        return $max_id + 1;
    }
}
