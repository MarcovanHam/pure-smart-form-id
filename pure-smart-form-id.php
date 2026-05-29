<?php
/**
 * Plugin Name:       Pure Smart Form ID
 * Plugin URI:        https://pureplugins.eu
 * Description:       Smart form tokens for cross-form pre-fill, multi-step funnels and CRM integration. Works with Fluent Forms and Gravity Forms. GDPR-friendly: no personal data in URLs.
 * Version:           1.5.0
 * Author:            Marco van Ham
 * Author URI:        https://www.marcovanham.nl
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pure-smart-form-id
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

defined( 'ABSPATH' ) || exit;

// Constanten
define( 'PSFID_VERSION', '1.5.0' );
define( 'PSFID_FILE',    __FILE__ );
define( 'PSFID_PATH',    plugin_dir_path( __FILE__ ) );
define( 'PSFID_URL',     plugin_dir_url( __FILE__ ) );
define( 'PSFID_BASENAME', plugin_basename( __FILE__ ) );

// Activatie / Deactivatie
require_once PSFID_PATH . 'includes/class-activator.php';
register_activation_hook( __FILE__,   [ 'PSFID_Activator', 'activeer' ] );
register_deactivation_hook( __FILE__, [ 'PSFID_Activator', 'deactiveer' ] );
register_uninstall_hook( __FILE__,    [ 'PSFID_Activator', 'verwijder' ] );

// Bootstrap
add_action( 'plugins_loaded', function () {
    // Vertalingen
    load_plugin_textdomain( 'pure-smart-form-id', false, dirname( PSFID_BASENAME ) . '/languages' );

    // Versie-check / migratie
    PSFID_Activator::check_versie();

    require_once PSFID_PATH . 'includes/class-plugin.php';
    PSFID_Plugin::instantie()->initialiseer();
} );

// Plugin-actie links (Help / Instellingen)
add_filter( 'plugin_action_links_' . PSFID_BASENAME, function ( $links ) {
    $settings_url = admin_url( 'admin.php?page=psfid-settings' );
    $help_url     = admin_url( 'admin.php?page=psfid-help' );

    array_unshift(
        $links,
        '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'pure-smart-form-id' ) . '</a>',
        '<a href="' . esc_url( $help_url ) . '">' . esc_html__( 'Help', 'pure-smart-form-id' ) . '</a>'
    );

    return $links;
} );
