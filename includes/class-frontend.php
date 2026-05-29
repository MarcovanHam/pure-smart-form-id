<?php
/**
 * Frontend: enqueue JS voor auto-fill, cookie sync, en shortcode dynamisch updaten.
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        // Skip in admin
        if ( is_admin() ) {
            return;
        }

        wp_register_script(
            'psfid-frontend',
            PSFID_URL . 'assets/public/psfid.js',
            [],
            PSFID_VERSION,
            true
        );

        $instellingen = PSFID_Plugin::get_settings();
        $token        = PSFID_Resolver::get_current();
        $velden       = PSFID_Resolver::get_current_fields();

        wp_localize_script( 'psfid-frontend', 'PSFID', [
            'restUrl'      => esc_url_raw( rest_url( 'psfid/v1/' ) ),
            'cookieName'   => $instellingen['cookie_name'] ?? 'psfid_token',
            'urlParam'     => $instellingen['url_param'] ?? 'id',
            'tokenField'   => $instellingen['token_field_name'] ?? 'form_token',
            'cookieDays'   => (int) ( $instellingen['cookie_lifetime_days'] ?? 30 ),
            'token'        => $token ? $token->token : '',
            'fields'       => (object) $velden,
            'aliases'      => (object) ( $instellingen['field_aliases'] ?? [] ),
        ] );

        wp_enqueue_script( 'psfid-frontend' );
    }
}
