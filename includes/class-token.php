<?php
/**
 * Token klasse: genereren, resolven (URL/cookie/email/telefoon), expiry, revoke.
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Token {

    /**
     * Genereer een uniek random token (16 chars URL-safe).
     */
    public static function genereer(): string {
        global $wpdb;

        do {
            $bytes = random_bytes( 12 );
            // base64 url-safe, geen padding
            $token = rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
            // 12 bytes = 16 chars
            $bestaat = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}psfid_tokens WHERE token = %s",
                $token
            ) );
        } while ( $bestaat > 0 );

        return $token;
    }

    /**
     * Maak een nieuw token-record aan.
     */
    public static function maak_record( ?string $email = null, ?string $phone = null, ?string $source = null ): int {
        global $wpdb;

        $instellingen = PSFID_Plugin::get_settings();
        $now          = current_time( 'mysql' );
        $expiry_days  = (int) ( $instellingen['token_expiry_days'] ?? 365 );
        $expires      = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );

        $token = self::genereer();

        $wpdb->insert(
            $wpdb->prefix . 'psfid_tokens',
            [
                'token'      => $token,
                'email'      => $email,
                'phone'      => $phone,
                'source'     => $source,
                'ip_hash'    => self::hash_ip(),
                'user_agent' => substr( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 255 ),
                'created_at' => $now,
                'updated_at' => $now,
                'expires_at' => $expires,
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        $token_id = (int) $wpdb->insert_id;
        PSFID_Log::schrijf( $token_id, 'created', $source );

        return $token_id;
    }

    /**
     * Zoek token-record op de token-string. Respecteert expiry.
     */
    public static function zoek_op_token( string $token ): ?object {
        global $wpdb;

        if ( empty( $token ) ) {
            return null;
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psfid_tokens
             WHERE token = %s
             AND ( expires_at IS NULL OR expires_at > %s )
             LIMIT 1",
            $token,
            current_time( 'mysql' )
        ) );

        return $row ?: null;
    }

    /**
     * Zoek token-record op interne ID.
     */
    public static function zoek_op_id( int $token_id ): ?object {
        global $wpdb;

        if ( $token_id <= 0 ) {
            return null;
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psfid_tokens WHERE id = %d LIMIT 1",
            $token_id
        ) );

        return $row ?: null;
    }

    /**
     * Zoek token-record op email-adres. Voor email-fallback resolution.
     */
    public static function zoek_op_email( string $email ): ?object {
        global $wpdb;

        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return null;
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psfid_tokens
             WHERE email = %s
             AND ( expires_at IS NULL OR expires_at > %s )
             ORDER BY updated_at DESC
             LIMIT 1",
            $email,
            current_time( 'mysql' )
        ) );

        return $row ?: null;
    }

    /**
     * Zoek token-record op telefoonnummer (genormaliseerd).
     */
    public static function zoek_op_phone( string $phone ): ?object {
        global $wpdb;

        $phone = self::normaliseer_phone( $phone );
        if ( strlen( $phone ) < 6 ) {
            return null;
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psfid_tokens
             WHERE phone = %s
             AND ( expires_at IS NULL OR expires_at > %s )
             ORDER BY updated_at DESC
             LIMIT 1",
            $phone,
            current_time( 'mysql' )
        ) );

        return $row ?: null;
    }

    /**
     * De resolution chain: URL -> cookie -> email -> phone -> nieuw.
     * Geeft het token-record (object) terug, of null.
     *
     * $form_data is een associative array met de zojuist ingediende velden
     * (alleen relevant voor de email/phone fallback).
     */
    public static function resolve( array $form_data = [] ): ?object {
        $instellingen = PSFID_Plugin::get_settings();

        // Stap 1: URL parameter
        $url_param = $instellingen['url_param'] ?? 'id';
        if ( ! empty( $_GET[ $url_param ] ) ) {
            $token = sanitize_text_field( wp_unslash( $_GET[ $url_param ] ) );
            $rec   = self::zoek_op_token( $token );
            if ( $rec ) {
                return $rec;
            }
        }

        // Stap 2: Cookie
        $cookie_name = $instellingen['cookie_name'] ?? 'psfid_token';
        if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
            $token = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
            $rec   = self::zoek_op_token( $token );
            if ( $rec ) {
                return $rec;
            }
        }

        // Stap 3: Email fallback (alleen als enabled)
        if ( ! empty( $instellingen['email_lookup_enabled'] ) ) {
            $email = self::extract_email( $form_data );
            if ( $email ) {
                $rec = self::zoek_op_email( $email );
                if ( $rec ) {
                    PSFID_Log::schrijf( (int) $rec->id, 'email_match', $email );
                    return $rec;
                }
            }
        }

        // Stap 4: Phone fallback (alleen als enabled)
        if ( ! empty( $instellingen['phone_lookup_enabled'] ) ) {
            $phone = self::extract_phone( $form_data );
            if ( $phone ) {
                $rec = self::zoek_op_phone( $phone );
                if ( $rec ) {
                    PSFID_Log::schrijf( (int) $rec->id, 'phone_match', $phone );
                    return $rec;
                }
            }
        }

        return null;
    }

    /**
     * Update updated_at + optioneel email/phone op token-record.
     */
    public static function raak_aan( int $token_id, ?string $email = null, ?string $phone = null ): void {
        global $wpdb;

        $update = [ 'updated_at' => current_time( 'mysql' ) ];
        $format = [ '%s' ];

        if ( $email && is_email( $email ) ) {
            $update['email'] = $email;
            $format[]        = '%s';
        }
        if ( $phone ) {
            $update['phone'] = self::normaliseer_phone( $phone );
            $format[]        = '%s';
        }

        $wpdb->update(
            $wpdb->prefix . 'psfid_tokens',
            $update,
            [ 'id' => $token_id ],
            $format,
            [ '%d' ]
        );
    }

    /**
     * Verleng de expiry datum (bij elke update).
     */
    public static function verleng_expiry( int $token_id ): void {
        global $wpdb;

        $instellingen = PSFID_Plugin::get_settings();
        $expiry_days  = (int) ( $instellingen['token_expiry_days'] ?? 365 );
        $expires      = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );

        $wpdb->update(
            $wpdb->prefix . 'psfid_tokens',
            [ 'expires_at' => $expires ],
            [ 'id' => $token_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Verwijder een token-record (cascadeert naar data via FK of expliciet).
     */
    public static function verwijder( int $token_id ): bool {
        global $wpdb;

        $wpdb->delete( $wpdb->prefix . 'psfid_data',   [ 'token_id' => $token_id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'psfid_log',    [ 'token_id' => $token_id ], [ '%d' ] );
        $resultaat = $wpdb->delete( $wpdb->prefix . 'psfid_tokens', [ 'id' => $token_id ], [ '%d' ] );

        return (bool) $resultaat;
    }

    /**
     * Helpers
     */

    private static function extract_email( array $form_data ): ?string {
        // Probeer alle gangbare email keys
        $kandidaten = [ 'email', 'email_address', 'mail', 'e-mail', 'user_email', 'emailadres' ];
        foreach ( $kandidaten as $key ) {
            if ( ! empty( $form_data[ $key ] ) ) {
                $val = sanitize_email( (string) $form_data[ $key ] );
                if ( is_email( $val ) ) {
                    return $val;
                }
            }
        }
        // Of zoek naar elke waarde die op email lijkt
        foreach ( $form_data as $val ) {
            if ( is_string( $val ) && is_email( $val ) ) {
                return sanitize_email( $val );
            }
        }
        return null;
    }

    private static function extract_phone( array $form_data ): ?string {
        $kandidaten = [ 'phone', 'telefoon', 'tel', 'mobiel', 'mobile', 'telephone' ];
        foreach ( $kandidaten as $key ) {
            if ( ! empty( $form_data[ $key ] ) ) {
                return self::normaliseer_phone( (string) $form_data[ $key ] );
            }
        }
        return null;
    }

    public static function normaliseer_phone( string $phone ): string {
        // Verwijder alles behalve cijfers en plus
        $clean = preg_replace( '/[^\d+]/', '', $phone );
        return substr( (string) $clean, 0, 50 );
    }

    private static function hash_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ( ! $ip ) {
            return '';
        }
        // Hash met salt zodat het geen direct identificeerbare data is in DB
        return hash( 'sha256', $ip . wp_salt( 'auth' ) );
    }
}
