<?php
/**
 * Wordt aangeroepen als de plugin wordt verwijderd via WordPress.
 * Wist optioneel alle tabellen en options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$instellingen = get_option( 'psfid_settings', [] );

// Alleen verwijderen als gebruiker dit expliciet heeft aangevinkt
if ( ! empty( $instellingen['delete_on_uninstall'] ) ) {
    global $wpdb;

    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psfid_tokens" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psfid_data" );
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}psfid_log" );

    delete_option( 'psfid_settings' );
    delete_option( 'psfid_branding' );
    delete_option( 'psfid_versie' );

    wp_clear_scheduled_hook( 'psfid_cleanup_cron' );
}
