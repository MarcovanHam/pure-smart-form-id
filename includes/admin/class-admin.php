<?php
/**
 * Admin orchestrator: menu, assets, AJAX, formulier-handlers.
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'admin_init',            [ $this, 'handle_actions' ] );
        add_action( 'admin_init',            [ $this, 'handle_csv_export' ] );
        add_action( 'wp_dashboard_setup',    [ $this, 'add_wp_dashboard_widget' ] );
        add_filter( 'admin_footer_text',     [ $this, 'admin_footer_text' ] );

        // AJAX
        add_action( 'wp_ajax_psfid_test_ac',  [ $this, 'ajax_test_ac' ] );
        add_action( 'wp_ajax_psfid_delete_token', [ $this, 'ajax_delete_token' ] );
    }

    public function menu(): void {
        $branding = PSFID_Plugin::get_branding();
        $label    = $branding['menu_label'] ?: 'Form ID';
        $cap      = 'manage_options';

        add_menu_page(
            $branding['plugin_name'] ?: 'Pure Smart Form ID',
            $label,
            $cap,
            'psfid-dashboard',
            [ $this, 'render_dashboard' ],
            'dashicons-id-alt',
            58
        );

        add_submenu_page( 'psfid-dashboard', __( 'Dashboard', 'pure-smart-form-id' ),    __( 'Dashboard', 'pure-smart-form-id' ),    $cap, 'psfid-dashboard',     [ $this, 'render_dashboard' ] );
        add_submenu_page( 'psfid-dashboard', __( 'Tokens', 'pure-smart-form-id' ),       __( 'Tokens', 'pure-smart-form-id' ),       $cap, 'psfid-tokens',        [ $this, 'render_tokens' ] );
        add_submenu_page( 'psfid-dashboard', __( 'Fields', 'pure-smart-form-id' ),       __( 'Fields', 'pure-smart-form-id' ),       $cap, 'psfid-fields',        [ $this, 'render_fields' ] );
        add_submenu_page( 'psfid-dashboard', __( 'Integrations', 'pure-smart-form-id' ), __( 'Integrations', 'pure-smart-form-id' ), $cap, 'psfid-integrations',  [ $this, 'render_integrations' ] );
        add_submenu_page( 'psfid-dashboard', __( 'Settings', 'pure-smart-form-id' ),     __( 'Settings', 'pure-smart-form-id' ),     $cap, 'psfid-settings',      [ $this, 'render_settings' ] );
        add_submenu_page( 'psfid-dashboard', __( 'Help', 'pure-smart-form-id' ),         __( 'Help', 'pure-smart-form-id' ),         $cap, 'psfid-help',          [ $this, 'render_help' ] );
    }

    public function enqueue( $hook ): void {
        // Op het WP hoofd-dashboard alleen de CSS laden voor het widget
        if ( $hook === 'index.php' ) {
            wp_enqueue_style(
                'psfid-admin',
                PSFID_URL . 'assets/admin/admin.css',
                [],
                PSFID_VERSION
            );
            return;
        }

        if ( strpos( (string) $hook, 'psfid' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'psfid-admin',
            PSFID_URL . 'assets/admin/admin.css',
            [],
            PSFID_VERSION
        );

        wp_enqueue_script(
            'psfid-admin',
            PSFID_URL . 'assets/admin/admin.js',
            [ 'jquery' ],
            PSFID_VERSION,
            true
        );

        wp_localize_script( 'psfid-admin', 'PSFID_ADMIN', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'psfid_admin' ),
            'i18n'    => [
                'confirm_delete' => __( 'Are you sure you want to delete this token?', 'pure-smart-form-id' ),
                'testing'        => __( 'Testing...', 'pure-smart-form-id' ),
                'connected_as'   => __( 'Connected:', 'pure-smart-form-id' ),
                'failed'         => __( 'Failed:', 'pure-smart-form-id' ),
                'unknown_error'  => __( 'unknown error', 'pure-smart-form-id' ),
                'fill_url_key'   => __( 'Please enter both URL and API key', 'pure-smart-form-id' ),
                'connection_failed' => __( 'Connection failed', 'pure-smart-form-id' ),
                'remove'         => __( 'Remove', 'pure-smart-form-id' ),
            ],
        ] );
    }

    /**
     * Form submits afhandelen (settings, branding, fields, etc).
     */
    public function handle_actions(): void {
        if ( ! isset( $_POST['psfid_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $action = sanitize_key( $_POST['psfid_action'] );
        check_admin_referer( 'psfid_' . $action );

        switch ( $action ) {
            case 'save_settings':
                $this->save_settings();
                break;
            case 'save_fields':
                $this->save_fields();
                break;
            case 'save_integrations':
                $this->save_integrations();
                break;
            case 'run_cleanup':
                $this->run_cleanup_now();
                break;
            case 'delete_all_tokens':
                $this->delete_all_tokens();
                break;
        }
    }

    private function delete_all_tokens(): void {
        global $wpdb;

        // Tel eerst voor de notice
        $aantal = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}psfid_tokens" );

        // Truncate alle 3 tabellen
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}psfid_data" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}psfid_log" );
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}psfid_tokens" );

        PSFID_Log::schrijf( null, 'delete_all', "Bulk delete: $aantal tokens removed" );

        wp_safe_redirect( add_query_arg( [ 'page' => 'psfid-dashboard', 'deleted_all' => $aantal ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private function save_settings(): void {
        $current = (array) get_option( 'psfid_settings', [] );

        $nieuwe = [
            'url_param'             => sanitize_key( $_POST['url_param'] ?? 'id' ),
            'cookie_name'           => sanitize_key( $_POST['cookie_name'] ?? 'psfid_token' ),
            'cookie_lifetime_days'  => max( 1, (int) ( $_POST['cookie_lifetime_days'] ?? 30 ) ),
            'token_expiry_days'     => max( 1, (int) ( $_POST['token_expiry_days'] ?? 365 ) ),
            'data_retention_days'   => max( 1, (int) ( $_POST['data_retention_days'] ?? 90 ) ),
            'log_retention_days'    => max( 0, (int) ( $_POST['log_retention_days'] ?? 30 ) ),
            'token_field_name'      => sanitize_key( $_POST['token_field_name'] ?? 'form_token' ),
            'auto_inject_token'     => ! empty( $_POST['auto_inject_token'] ) ? 1 : 0,
            'email_lookup_enabled'  => ! empty( $_POST['email_lookup_enabled'] ) ? 1 : 0,
            'phone_lookup_enabled'  => ! empty( $_POST['phone_lookup_enabled'] ) ? 1 : 0,
            'auto_link_wp_user'     => ! empty( $_POST['auto_link_wp_user'] ) ? 1 : 0,
            'fc_source_enabled'     => ! empty( $_POST['fc_source_enabled'] ) ? 1 : 0,
            'fc_url_lookup_enabled' => ! empty( $_POST['fc_url_lookup_enabled'] ) ? 1 : 0,
            'fc_url_param'          => sanitize_key( $_POST['fc_url_param'] ?? 'rid' ) ?: 'rid',
            'fc_url_field'          => sanitize_key( $_POST['fc_url_field'] ?? '' ),
            'cookie_consent_required' => ! empty( $_POST['cookie_consent_required'] ) ? 1 : 0,
            'delete_on_uninstall'   => ! empty( $_POST['delete_on_uninstall'] ) ? 1 : 0,
        ];

        $merged = array_merge( $current, $nieuwe );
        update_option( 'psfid_settings', $merged );
        PSFID_Plugin::clear_settings_cache();

        $this->success_redirect( 'psfid-settings' );
    }

    private function save_fields(): void {
        $current = (array) get_option( 'psfid_settings', [] );

        $aliases_raw = $_POST['aliases'] ?? [];
        $aliases     = [];
        if ( is_array( $aliases_raw ) ) {
            foreach ( $aliases_raw as $row ) {
                $alias = sanitize_key( strtolower( $row['alias'] ?? '' ) );
                $real  = sanitize_key( strtolower( $row['real'] ?? '' ) );
                if ( $alias && $real ) {
                    $aliases[ $alias ] = $real;
                }
            }
        }

        $allowed_raw = sanitize_textarea_field( $_POST['allowed_fields'] ?? '' );
        $allowed     = array_filter( array_map(
            fn( $s ) => sanitize_key( strtolower( trim( $s ) ) ),
            preg_split( '/[\r\n,]+/', $allowed_raw )
        ) );

        $current['field_aliases']  = $aliases;
        $current['allowed_fields'] = array_values( array_unique( $allowed ) );

        update_option( 'psfid_settings', $current );
        PSFID_Plugin::clear_settings_cache();
        $this->success_redirect( 'psfid-fields' );
    }

    private function save_integrations(): void {
        $current = (array) get_option( 'psfid_settings', [] );

        $current['enable_fluent_forms']  = ! empty( $_POST['enable_fluent_forms'] ) ? 1 : 0;
        $current['enable_gravity_forms'] = ! empty( $_POST['enable_gravity_forms'] ) ? 1 : 0;
        $current['crm_provider']         = in_array( $_POST['crm_provider'] ?? 'none', [ 'none', 'fluentcrm', 'activecampaign' ], true )
            ? $_POST['crm_provider'] : 'none';
        $current['ac_api_url']  = esc_url_raw( $_POST['ac_api_url'] ?? '' );
        $current['ac_api_key']  = sanitize_text_field( $_POST['ac_api_key'] ?? '' );
        $current['ac_list_id']  = (int) ( $_POST['ac_list_id'] ?? 0 );
        $current['ac_field_id'] = (int) ( $_POST['ac_field_id'] ?? 0 );

        // FluentCRM token-field slug (waar de token naartoe geschreven wordt)
        $slug = sanitize_key( $_POST['fc_token_field_slug'] ?? 'psfid_token' );
        $current['fc_token_field_slug'] = $slug !== '' ? $slug : 'psfid_token';

        update_option( 'psfid_settings', $current );
        PSFID_Plugin::clear_settings_cache();

        // Bij FluentCRM: zorg dat custom field bestaat
        if ( $current['crm_provider'] === 'fluentcrm' && function_exists( 'FluentCrmApi' ) ) {
            require_once PSFID_PATH . 'includes/integrations/class-crm-fluentcrm.php';
            PSFID_CRM_FluentCRM::zorg_custom_field_bestaat( $current['fc_token_field_slug'] );
        }

        $this->success_redirect( 'psfid-integrations' );
    }

    private function run_cleanup_now(): void {
        $verwijderd = PSFID_Store::ruim_op_retentie();
        PSFID_Log::schrijf( null, 'manual_cleanup', "Handmatig: $verwijderd verwijderd" );
        wp_safe_redirect( add_query_arg( [ 'page' => 'psfid-dashboard', 'cleaned' => $verwijderd ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * CSV export.
     */
    public function handle_csv_export(): void {
        if ( ! isset( $_GET['psfid_export'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        check_admin_referer( 'psfid_export' );

        $type = sanitize_key( $_GET['psfid_export'] );
        if ( $type === 'all' ) {
            $this->export_alle_tokens();
        } elseif ( $type === 'single' && ! empty( $_GET['token_id'] ) ) {
            $this->export_token( (int) $_GET['token_id'] );
        }
    }

    private function export_alle_tokens(): void {
        global $wpdb;

        $tokens = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}psfid_tokens ORDER BY updated_at DESC", ARRAY_A );
        $data   = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}psfid_data", ARRAY_A );

        // Groepeer data per token
        $data_per_token = [];
        $alle_keys = [];
        foreach ( $data as $r ) {
            $data_per_token[ $r['token_id'] ][ $r['field_key'] ] = $r['field_value'];
            $alle_keys[ $r['field_key'] ] = true;
        }
        $alle_keys = array_keys( $alle_keys );
        sort( $alle_keys );

        // Headers
        $headers = array_merge(
            [ 'token', 'email', 'phone', 'source', 'created_at', 'updated_at', 'expires_at' ],
            $alle_keys
        );

        $filename = 'psfid-export-' . date( 'Y-m-d-His' ) . '.csv';
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );
        // BOM voor Excel
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, $headers );

        foreach ( $tokens as $t ) {
            $row = [
                $t['token'],
                $t['email'],
                $t['phone'],
                $t['source'],
                $t['created_at'],
                $t['updated_at'],
                $t['expires_at'],
            ];
            foreach ( $alle_keys as $k ) {
                $row[] = $data_per_token[ $t['id'] ][ $k ] ?? '';
            }
            fputcsv( $out, $row );
        }

        fclose( $out );
        exit;
    }

    private function export_token( int $token_id ): void {
        $rec = PSFID_Store::haal_volledig_record_op( $token_id );
        if ( ! $rec ) {
            wp_die( esc_html__( 'Token not found', 'pure-smart-form-id' ) );
        }

        $filename = 'psfid-token-' . $rec['token'] . '.csv';
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );

        fputcsv( $out, [ 'field', 'value' ] );
        fputcsv( $out, [ 'token', $rec['token'] ] );
        fputcsv( $out, [ 'email', $rec['email'] ] );
        fputcsv( $out, [ 'phone', $rec['phone'] ] );
        fputcsv( $out, [ 'source', $rec['source'] ] );
        fputcsv( $out, [ 'created_at', $rec['created_at'] ] );
        fputcsv( $out, [ 'updated_at', $rec['updated_at'] ] );
        fputcsv( $out, [ 'expires_at', $rec['expires_at'] ] );
        foreach ( $rec['fields'] as $k => $v ) {
            fputcsv( $out, [ $k, $v ] );
        }

        fclose( $out );
        exit;
    }

    /**
     * AJAX: ActiveCampaign verbinding testen.
     */
    public function ajax_test_ac(): void {
        check_ajax_referer( 'psfid_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied', 'pure-smart-form-id' ) );
        }

        $url = esc_url_raw( $_POST['url'] ?? '' );
        $key = sanitize_text_field( $_POST['key'] ?? '' );

        require_once PSFID_PATH . 'includes/integrations/class-crm-activecampaign.php';
        $resultaat = PSFID_CRM_ActiveCampaign::test_verbinding( $url, $key );

        if ( $resultaat['success'] ) {
            $lijsten = PSFID_CRM_ActiveCampaign::haal_lijsten( $url, $key );
            $velden  = PSFID_CRM_ActiveCampaign::haal_custom_fields( $url, $key );
            wp_send_json_success( [
                'message' => $resultaat['message'],
                'lists'   => $lijsten,
                'fields'  => $velden,
            ] );
        } else {
            wp_send_json_error( $resultaat['message'] );
        }
    }

    /**
     * AJAX: Token verwijderen.
     */
    public function ajax_delete_token(): void {
        check_ajax_referer( 'psfid_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Access denied', 'pure-smart-form-id' ) );
        }
        $id = (int) ( $_POST['token_id'] ?? 0 );
        if ( $id <= 0 ) {
            wp_send_json_error( __( 'Invalid ID', 'pure-smart-form-id' ) );
        }
        PSFID_Token::verwijder( $id );
        wp_send_json_success();
    }

    /* ---------- Render functies ---------- */

    public function render_dashboard(): void {
        require PSFID_PATH . 'includes/admin/views/dashboard.php';
    }
    public function render_tokens(): void {
        require PSFID_PATH . 'includes/admin/views/tokens.php';
    }
    public function render_fields(): void {
        require PSFID_PATH . 'includes/admin/views/fields.php';
    }
    public function render_integrations(): void {
        require PSFID_PATH . 'includes/admin/views/integrations.php';
    }
    public function render_settings(): void {
        require PSFID_PATH . 'includes/admin/views/settings.php';
    }
    public function render_help(): void {
        require PSFID_PATH . 'includes/admin/views/help.php';
    }

    private function success_redirect( string $page ): void {
        wp_safe_redirect( add_query_arg( [ 'page' => $page, 'updated' => 1 ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Voegt het Pure Smart Form ID widget toe aan het WordPress hoofd-Dashboard.
     */
    public function add_wp_dashboard_widget(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $branding = PSFID_Plugin::get_branding();
        $title    = $branding['plugin_name'] ?: 'Pure Smart Form ID';

        wp_add_dashboard_widget(
            'psfid_dashboard_widget',
            $title,
            [ $this, 'render_wp_dashboard_widget' ]
        );
    }

    /**
     * Render functie voor het WP Dashboard widget.
     */
    public function render_wp_dashboard_widget(): void {
        require PSFID_PATH . 'includes/admin/views/wp-dashboard-widget.php';
    }

    /**
     * Custom admin footer text op PSFID-pagina's. Op andere admin-paginas
     * wordt de standaard WordPress footer behouden.
     */
    public function admin_footer_text( $text ) {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || strpos( (string) $screen->id, 'psfid' ) === false ) {
            return $text;
        }

        // Als constants HIDE_CREDITS aanstaan, gebruik standaard text
        if ( defined( 'PSFID_BRAND_HIDE_CREDITS' ) && PSFID_BRAND_HIDE_CREDITS ) {
            return $text;
        }

        return sprintf(
            /* translators: 1: plugin name + version, 2: author name with link, 3: brand site link */
            __( '%1$s &middot; Made by %2$s &middot; %3$s', 'pure-smart-form-id' ),
            'Pure Smart Form ID v' . PSFID_VERSION,
            '<a href="https://www.marcovanham.nl" target="_blank" rel="noopener">Marco van Ham</a>',
            '<a href="https://pureplugins.eu" target="_blank" rel="noopener">pureplugins.eu</a>'
        );
    }
}
