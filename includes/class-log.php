<?php
/**
 * Audit log voor token-acties.
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Log {

    public static function schrijf( ?int $token_id, string $action, $details = null ): void {
        global $wpdb;

        $details_str = '';
        if ( is_string( $details ) ) {
            $details_str = $details;
        } elseif ( $details !== null ) {
            $details_str = wp_json_encode( $details );
        }

        $wpdb->insert(
            $wpdb->prefix . 'psfid_log',
            [
                'token_id'   => $token_id,
                'action'     => substr( $action, 0, 50 ),
                'details'    => $details_str,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );
    }

    public static function haal_op( int $token_id, int $limit = 50 ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psfid_log
             WHERE token_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $token_id,
            $limit
        ) );
    }

    public static function ruim_op( int $dagen ): int {
        global $wpdb;

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $dagen * DAY_IN_SECONDS ) );

        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}psfid_log WHERE created_at < %s",
            $cutoff
        ) );
    }
}
