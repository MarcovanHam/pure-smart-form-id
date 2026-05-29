<?php
/**
 * Activator: tabellen aanmaken, cron plannen, defaults zetten, migraties.
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Activator {

    public static function activeer(): void {
        self::maak_tabellen();
        self::zet_defaults();
        self::plan_cron();
        update_option( 'psfid_versie', PSFID_VERSION );
        flush_rewrite_rules();
    }

    public static function deactiveer(): void {
        wp_clear_scheduled_hook( 'psfid_cleanup_cron' );
    }

    public static function verwijder(): void {
        // Wordt alleen uitgevoerd via uninstall.php (zie register_uninstall_hook)
        $instellingen = get_option( 'psfid_settings', [] );
        if ( ! empty( $instellingen['delete_on_uninstall'] ) ) {
            global $wpdb;
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psfid_tokens" );
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psfid_data" );
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psfid_log" );
            delete_option( 'psfid_settings' );
            delete_option( 'psfid_versie' );
            delete_option( 'psfid_branding' );
        }
    }

    public static function check_versie(): void {
        $opgeslagen = get_option( 'psfid_versie', '0.0.0' );
        if ( version_compare( $opgeslagen, PSFID_VERSION, '<' ) ) {
            self::maak_tabellen();
            self::zet_defaults();
            update_option( 'psfid_versie', PSFID_VERSION );
        }
    }

    private static function maak_tabellen(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tokens = "CREATE TABLE {$wpdb->prefix}psfid_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) NOT NULL,
            email VARCHAR(190) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            source VARCHAR(50) DEFAULT NULL,
            ip_hash VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            crm_synced TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            expires_at DATETIME DEFAULT NULL,
            UNIQUE KEY uk_token (token),
            KEY idx_email (email),
            KEY idx_phone (phone),
            KEY idx_expires (expires_at),
            KEY idx_updated (updated_at)
        ) $charset;";

        $data = "CREATE TABLE {$wpdb->prefix}psfid_data (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token_id BIGINT UNSIGNED NOT NULL,
            field_key VARCHAR(100) NOT NULL,
            field_value LONGTEXT,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uk_token_field (token_id, field_key),
            KEY idx_token_id (token_id)
        ) $charset;";

        $log = "CREATE TABLE {$wpdb->prefix}psfid_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token_id BIGINT UNSIGNED DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT,
            created_at DATETIME NOT NULL,
            KEY idx_token_id (token_id),
            KEY idx_created (created_at)
        ) $charset;";

        dbDelta( $tokens );
        dbDelta( $data );
        dbDelta( $log );
    }

    private static function zet_defaults(): void {
        $defaults = [
            // URL en cookie
            'url_param'             => 'id',
            'cookie_name'           => 'psfid_token',
            'cookie_lifetime_days'  => 30,

            // Token expiry / retentie
            'token_expiry_days'     => 365,    // Token in URL geldig
            'data_retention_days'   => 90,     // Data wissen na X dagen geen update
            'log_retention_days'    => 30,

            // Form integraties
            'enable_fluent_forms'   => 1,
            'enable_gravity_forms'  => 1,
            'auto_inject_token'     => 1,
            'token_field_name'      => 'form_token',

            // Email / phone fallback
            'email_lookup_enabled'  => 1,
            'phone_lookup_enabled'  => 0,

            // WP-user auto-link en FluentCRM-source (nieuw in 1.3.0)
            'auto_link_wp_user'     => 1,  // herken ingelogde WP-gebruikers via email
            'fc_source_enabled'     => 1,  // merge FluentCRM contact-velden als FC actief is

            // FluentCRM URL-lookup via custom field (nieuw in 1.4.0)
            'fc_url_lookup_enabled' => 0,            // opt-in: herken ?rid= via FC custom field
            'fc_url_param'          => 'rid',        // URL-parameter naam
            'fc_url_field'          => '',           // FC custom field slug om op te matchen
            'fc_token_field_slug'   => 'psfid_token', // FC custom field om token in te schrijven

            // CRM
            'crm_provider'          => 'none',  // none, fluentcrm, activecampaign
            'ac_api_url'            => '',
            'ac_api_key'            => '',
            'ac_list_id'            => '',
            'ac_field_id'           => '',     // ActiveCampaign custom field ID voor token

            // GDPR
            'cookie_consent_required' => 0,
            'delete_on_uninstall'   => 0,

            // Veld aliassen (voornaam = first_name etc)
            'field_aliases'         => [
                'voornaam'    => 'first_name',
                'achternaam'  => 'last_name',
                'naam'        => 'first_name',
                'telefoon'    => 'phone',
                'tel'         => 'phone',
                'mail'        => 'email',
                'e-mail'      => 'email',
                'adres'       => 'address_line_1',
                'postcode'    => 'postal_code',
                'plaats'      => 'city',
                'woonplaats'  => 'city',
                'land'        => 'country',
            ],

            // Toegestane velden (whitelist, leeg = alles toegestaan)
            'allowed_fields'        => [],
        ];

        $bestaande = get_option( 'psfid_settings', [] );
        $merged    = wp_parse_args( $bestaande, $defaults );
        update_option( 'psfid_settings', $merged );

        // Branding defaults
        $branding_defaults = [
            'plugin_name'   => 'Pure Smart Form ID',
            'menu_label'    => 'Form ID',
            'support_url'   => '',
            'docs_url'      => '',
            'show_credits'  => 1,
        ];
        $branding = get_option( 'psfid_branding', [] );
        update_option( 'psfid_branding', wp_parse_args( $branding, $branding_defaults ) );
    }

    private static function plan_cron(): void {
        if ( ! wp_next_scheduled( 'psfid_cleanup_cron' ) ) {
            wp_schedule_event( time() + 3600, 'daily', 'psfid_cleanup_cron' );
        }
    }
}
