<?php
/**
 * Store: CRUD operations op token-data (key/value).
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Store {

    /**
     * Sla velden op voor een token. Bestaande velden worden geupdate.
     *
     * @param int   $token_id
     * @param array $velden  associative array key => value
     * @return int Aantal opgeslagen velden
     */
    public static function bewaar_velden( int $token_id, array $velden ): int {
        global $wpdb;

        if ( empty( $velden ) || $token_id <= 0 ) {
            return 0;
        }

        $instellingen = PSFID_Plugin::get_settings();
        $aliases      = (array) ( $instellingen['field_aliases'] ?? [] );
        $allowed      = (array) ( $instellingen['allowed_fields'] ?? [] );

        $aantal = 0;
        $now    = current_time( 'mysql' );

        foreach ( $velden as $key => $value ) {
            $key = sanitize_key( strtolower( (string) $key ) );
            if ( $key === '' ) {
                continue;
            }

            // Skip systeemvelden
            if ( self::is_systeem_veld( $key ) ) {
                continue;
            }

            // Pas alias toe (bijv. voornaam -> first_name)
            if ( isset( $aliases[ $key ] ) ) {
                $key = sanitize_key( strtolower( $aliases[ $key ] ) );
            }

            // Whitelist-check (als ingesteld)
            if ( ! empty( $allowed ) && ! in_array( $key, $allowed, true ) ) {
                continue;
            }

            // Lege waardes overslaan
            if ( $value === null || $value === '' ) {
                continue;
            }

            // Arrays of objecten serialize'n
            if ( is_array( $value ) || is_object( $value ) ) {
                $value = wp_json_encode( $value );
            }

            $value = (string) $value;
            // Max 5000 chars per veld
            if ( strlen( $value ) > 5000 ) {
                $value = substr( $value, 0, 5000 );
            }

            // Upsert
            $wpdb->query( $wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}psfid_data (token_id, field_key, field_value, updated_at)
                 VALUES (%d, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = VALUES(updated_at)",
                $token_id,
                $key,
                $value,
                $now
            ) );

            $aantal++;
        }

        // Touch token (updated_at + verleng expiry)
        if ( $aantal > 0 ) {
            PSFID_Token::raak_aan( $token_id );
            PSFID_Token::verleng_expiry( $token_id );
        }

        return $aantal;
    }

    /**
     * Haal alle velden op voor een token (associative array).
     */
    public static function haal_velden_op( int $token_id ): array {
        global $wpdb;

        if ( $token_id <= 0 ) {
            return [];
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT field_key, field_value FROM {$wpdb->prefix}psfid_data WHERE token_id = %d",
            $token_id
        ) );

        $result = [];
        foreach ( (array) $rows as $row ) {
            $result[ $row->field_key ] = $row->field_value;
        }
        return $result;
    }

    /**
     * Haal token + velden gecombineerd op (voor admin / display).
     */
    public static function haal_volledig_record_op( int $token_id ): ?array {
        global $wpdb;

        $token = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psfid_tokens WHERE id = %d",
            $token_id
        ), ARRAY_A );

        if ( ! $token ) {
            return null;
        }

        $token['fields'] = self::haal_velden_op( $token_id );
        return $token;
    }

    /**
     * Haal alle tokens op (voor admin overzicht / CSV export).
     *
     * @param array $args zoals 'limit', 'offset', 'search', 'orderby', 'order'
     */
    public static function haal_alle_tokens( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'limit'   => 50,
            'offset'  => 0,
            'search'  => '',
            'orderby' => 'updated_at',
            'order'   => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $allowed_orderby = [ 'id', 'token', 'email', 'created_at', 'updated_at', 'expires_at' ];
        if ( ! in_array( $args['orderby'], $allowed_orderby, true ) ) {
            $args['orderby'] = 'updated_at';
        }
        $args['order'] = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $where  = '1=1';
        $params = [];

        if ( ! empty( $args['search'] ) ) {
            $like   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where .= ' AND ( token LIKE %s OR email LIKE %s OR phone LIKE %s )';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $params[] = (int) $args['limit'];
        $params[] = (int) $args['offset'];

        $sql = "SELECT * FROM {$wpdb->prefix}psfid_tokens
                WHERE $where
                ORDER BY {$args['orderby']} {$args['order']}
                LIMIT %d OFFSET %d";

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    public static function tel_tokens( string $search = '' ): int {
        global $wpdb;

        if ( $search === '' ) {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}psfid_tokens" );
        }

        $like = '%' . $wpdb->esc_like( $search ) . '%';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}psfid_tokens
             WHERE token LIKE %s OR email LIKE %s OR phone LIKE %s",
            $like,
            $like,
            $like
        ) );
    }

    /**
     * Haal de meest recente tokens op (voor het WP dashboard widget).
     */
    public static function recente_tokens( int $limit = 5 ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, token, email, phone, source, updated_at
             FROM {$wpdb->prefix}psfid_tokens
             ORDER BY updated_at DESC
             LIMIT %d",
            $limit
        ) );
        return $rows ?: [];
    }

    /**
     * Mask een email-adres voor weergave: marco@nomaxx.nl -> mar***@n***.nl
     * Bewaart privacy in screenshots/screenshares maar laat herkenbaar genoeg.
     */
    public static function mask_email( string $email ): string {
        if ( ! is_email( $email ) ) {
            return $email;
        }
        $parts = explode( '@', $email );
        if ( count( $parts ) !== 2 ) {
            return $email;
        }
        $local  = $parts[0];
        $domain = $parts[1];

        // Local: eerste 3 chars + ***
        $local_visible = substr( $local, 0, 3 );
        if ( strlen( $local ) > 3 ) {
            $local_visible .= '***';
        }

        // Domain: eerste char + *** + .tld
        $domain_parts = explode( '.', $domain );
        $tld          = array_pop( $domain_parts );
        $domain_main  = implode( '.', $domain_parts );
        $domain_visible = substr( $domain_main, 0, 1 ) . '***.' . $tld;

        return $local_visible . '@' . $domain_visible;
    }

    /**
     * Statistieken voor admin dashboard.
     */
    public static function statistieken(): array {
        global $wpdb;
        $now = current_time( 'mysql' );

        return [
            'totaal'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}psfid_tokens" ),
            'actief'       => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}psfid_tokens WHERE expires_at IS NULL OR expires_at > %s",
                $now
            ) ),
            'laatste_24u'  => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}psfid_tokens WHERE updated_at > %s",
                gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
            ) ),
            'laatste_7d'   => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}psfid_tokens WHERE updated_at > %s",
                gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS )
            ) ),
            'verlopen'     => (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}psfid_tokens WHERE expires_at < %s",
                $now
            ) ),
        ];
    }

    /**
     * Cleanup: verwijder tokens waarvan de data retentie verlopen is
     * (geen update binnen X dagen).
     */
    public static function ruim_op_retentie(): int {
        global $wpdb;

        $instellingen = PSFID_Plugin::get_settings();
        $dagen        = (int) ( $instellingen['data_retention_days'] ?? 90 );
        if ( $dagen < 1 ) {
            return 0;
        }

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $dagen * DAY_IN_SECONDS ) );

        // Haal IDs op
        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}psfid_tokens WHERE updated_at < %s",
            $cutoff
        ) );

        if ( empty( $ids ) ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}psfid_data WHERE token_id IN ($placeholders)",
            $ids
        ) );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}psfid_log WHERE token_id IN ($placeholders)",
            $ids
        ) );
        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}psfid_tokens WHERE id IN ($placeholders)",
            $ids
        ) );
    }

    /**
     * Velden die we niet willen opslaan (form internals etc).
     */
    private static function is_systeem_veld( string $key ): bool {
        $blacklist = [
            '_fluentform_id',
            '__fluent_form_emb',
            '_wp_http_referer',
            'g-recaptcha-response',
            'h-captcha-response',
            'cf-turnstile-response',
            '_wpnonce',
            'fluent_form_submit',
            'gform_target_page_number',
            'gform_source_page_number',
            'is_submit',
            'state',
        ];

        if ( in_array( $key, $blacklist, true ) ) {
            return true;
        }

        // Skip alles wat met underscore of double-underscore begint
        if ( str_starts_with( $key, '_' ) ) {
            return true;
        }

        // Skip GF interne velden zoals input_3_1
        if ( str_starts_with( $key, 'gform_' ) ) {
            return true;
        }

        return false;
    }
}
