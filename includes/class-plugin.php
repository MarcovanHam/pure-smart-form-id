<?php
/**
 * Hoofdklasse: laadt alle modules.
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Plugin {

    private static ?self $instantie = null;
    private static ?array $settings_cache = null;

    public static function instantie(): self {
        if ( null === self::$instantie ) {
            self::$instantie = new self();
        }
        return self::$instantie;
    }

    public function initialiseer(): void {
        // Core klassen laden
        require_once PSFID_PATH . 'includes/class-log.php';
        require_once PSFID_PATH . 'includes/class-token.php';
        require_once PSFID_PATH . 'includes/class-store.php';
        require_once PSFID_PATH . 'includes/class-cookie.php';
        require_once PSFID_PATH . 'includes/class-fc-source.php';
        require_once PSFID_PATH . 'includes/class-resolver.php';
        require_once PSFID_PATH . 'includes/class-frontend.php';
        require_once PSFID_PATH . 'includes/class-shortcode.php';

        // Resolver: bij elke pageload de huidige bezoeker matchen aan een token
        new PSFID_Resolver();

        // Frontend: JS injecteren voor auto-fill + cookie sync
        new PSFID_Frontend();

        // Shortcodes
        new PSFID_Shortcode();

        // Form-integraties (alleen laden als plugin actief is)
        $instellingen = self::get_settings();

        if ( ! empty( $instellingen['enable_fluent_forms'] ) && self::fluent_forms_actief() ) {
            require_once PSFID_PATH . 'includes/integrations/class-fluentforms.php';
            new PSFID_FluentForms();
        }

        if ( ! empty( $instellingen['enable_gravity_forms'] ) && self::gravity_forms_actief() ) {
            require_once PSFID_PATH . 'includes/integrations/class-gravityforms.php';
            new PSFID_GravityForms();
        }

        // CRM bridges
        $provider = $instellingen['crm_provider'] ?? 'none';
        if ( $provider === 'fluentcrm' && function_exists( 'FluentCrmApi' ) ) {
            require_once PSFID_PATH . 'includes/integrations/class-crm-fluentcrm.php';
            new PSFID_CRM_FluentCRM();
        } elseif ( $provider === 'activecampaign' ) {
            require_once PSFID_PATH . 'includes/integrations/class-crm-activecampaign.php';
            new PSFID_CRM_ActiveCampaign();
        }

        // Admin
        if ( is_admin() ) {
            require_once PSFID_PATH . 'includes/admin/class-admin.php';
            new PSFID_Admin();
        }

        // Cron handler
        add_action( 'psfid_cleanup_cron', [ $this, 'cleanup_cron' ] );

        // REST endpoint voor het ophalen van velden via JS (cross-pagina)
        add_action( 'rest_api_init', [ $this, 'register_rest' ] );
    }

    /**
     * Settings ophalen (gecached binnen request).
     */
    public static function get_settings(): array {
        if ( self::$settings_cache === null ) {
            self::$settings_cache = (array) get_option( 'psfid_settings', [] );
        }
        return self::$settings_cache;
    }

    public static function clear_settings_cache(): void {
        self::$settings_cache = null;
    }

    public static function get_branding(): array {
        $branding = (array) get_option( 'psfid_branding', [] );

        // Constants overrulen DB
        if ( defined( 'PSFID_BRAND_NAME' ) ) {
            $branding['plugin_name'] = PSFID_BRAND_NAME;
        }
        if ( defined( 'PSFID_BRAND_MENU' ) ) {
            $branding['menu_label'] = PSFID_BRAND_MENU;
        }
        if ( defined( 'PSFID_BRAND_SUPPORT_URL' ) ) {
            $branding['support_url'] = PSFID_BRAND_SUPPORT_URL;
        }
        if ( defined( 'PSFID_BRAND_DOCS_URL' ) ) {
            $branding['docs_url'] = PSFID_BRAND_DOCS_URL;
        }
        if ( defined( 'PSFID_BRAND_HIDE_CREDITS' ) && PSFID_BRAND_HIDE_CREDITS ) {
            $branding['show_credits'] = 0;
        }

        return $branding;
    }

    public static function fluent_forms_actief(): bool {
        return defined( 'FLUENTFORM_VERSION' ) || class_exists( '\FluentForm\Framework\Foundation\Application' );
    }

    public static function gravity_forms_actief(): bool {
        return class_exists( 'GFForms' ) || class_exists( 'GFCommon' );
    }

    public function cleanup_cron(): void {
        $instellingen = self::get_settings();

        // Data retentie
        $verwijderd = PSFID_Store::ruim_op_retentie();

        // Log retentie
        $log_dagen = (int) ( $instellingen['log_retention_days'] ?? 30 );
        if ( $log_dagen > 0 ) {
            PSFID_Log::ruim_op( $log_dagen );
        }

        if ( $verwijderd > 0 ) {
            PSFID_Log::schrijf( null, 'cleanup', "Verwijderd: $verwijderd tokens" );
        }
    }

    /**
     * REST endpoint zodat frontend JS data kan ophalen voor een token.
     */
    public function register_rest(): void {
        // GF draft uuid lookup (voor JS-fallback resume redirect bij Gutenberg blocks)
        register_rest_route( 'psfid/v1', '/gf-draft/(?P<token>[a-zA-Z0-9_-]+)/(?P<form_id>\d+)', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => function ( $request ) {
                global $wpdb;
                $token   = sanitize_text_field( $request['token'] );
                $form_id = (int) $request['form_id'];

                $rec = PSFID_Token::zoek_op_token( $token );
                if ( ! $rec || empty( $rec->email ) ) {
                    return new WP_REST_Response( [ 'error' => 'no_record' ], 404 );
                }

                $table = $wpdb->prefix . 'gf_draft_submissions';
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
                    return new WP_REST_Response( [ 'error' => 'no_drafts_table' ], 404 );
                }

                $email_lower = strtolower( trim( $rec->email ) );
                $draft = $wpdb->get_row( $wpdb->prepare(
                    "SELECT uuid FROM $table
                     WHERE form_id = %d AND LOWER(email) = %s
                     ORDER BY date_created DESC LIMIT 1",
                    $form_id,
                    $email_lower
                ) );

                if ( ! $draft || empty( $draft->uuid ) ) {
                    return new WP_REST_Response( [ 'error' => 'no_draft' ], 404 );
                }

                return new WP_REST_Response( [ 'uuid' => $draft->uuid ], 200 );
            },
        ] );

        register_rest_route( 'psfid/v1', '/data/(?P<token>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true', // Token zelf is de auth
            'callback'            => function ( $request ) {
                $token = sanitize_text_field( $request['token'] );
                $rec   = PSFID_Token::zoek_op_token( $token );
                if ( ! $rec ) {
                    return new WP_REST_Response( [ 'error' => 'not_found' ], 404 );
                }
                $velden = PSFID_Store::haal_velden_op( (int) $rec->id );

                // Voeg basis velden toe vanuit token-record (email/phone)
                if ( $rec->email && empty( $velden['email'] ) ) {
                    $velden['email'] = $rec->email;
                }
                if ( $rec->phone && empty( $velden['phone'] ) ) {
                    $velden['phone'] = $rec->phone;
                }

                // Pas aliassen toe (frontend kan zoeken op zowel voornaam als first_name)
                $instellingen = self::get_settings();
                $aliases      = (array) ( $instellingen['field_aliases'] ?? [] );
                foreach ( $aliases as $alias => $real_key ) {
                    if ( isset( $velden[ $real_key ] ) && ! isset( $velden[ $alias ] ) ) {
                        $velden[ $alias ] = $velden[ $real_key ];
                    }
                }

                return new WP_REST_Response( [
                    'token'  => $token,
                    'fields' => $velden,
                ], 200 );
            },
            'args' => [
                'token' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ] );
    }
}
