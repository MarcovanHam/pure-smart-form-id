<?php
/**
 * Cookie manager: token in cookie zetten/lezen.
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Cookie {

    public static function naam(): string {
        $instellingen = PSFID_Plugin::get_settings();
        return (string) ( $instellingen['cookie_name'] ?? 'psfid_token' );
    }

    public static function lifetime_seconds(): int {
        $instellingen = PSFID_Plugin::get_settings();
        $dagen        = (int) ( $instellingen['cookie_lifetime_days'] ?? 30 );
        if ( $dagen < 1 ) {
            $dagen = 30;
        }
        return $dagen * DAY_IN_SECONDS;
    }

    public static function lees(): ?string {
        $naam = self::naam();
        if ( empty( $_COOKIE[ $naam ] ) ) {
            return null;
        }
        return sanitize_text_field( wp_unslash( $_COOKIE[ $naam ] ) );
    }

    public static function zet( string $token ): void {
        if ( empty( $token ) ) {
            return;
        }

        // Cookie niet zetten als consent verplicht is en niet gegeven
        $instellingen = PSFID_Plugin::get_settings();
        if ( ! empty( $instellingen['cookie_consent_required'] ) && ! self::heeft_consent() ) {
            return;
        }

        // Headers al verzonden? Dan kunnen we via JS niet meer setten.
        // PHP setcookie alleen als headers nog niet zijn verzonden.
        if ( headers_sent() ) {
            return;
        }

        $expires = time() + self::lifetime_seconds();

        setcookie(
            self::naam(),
            $token,
            [
                'expires'  => $expires,
                'path'     => COOKIEPATH ?: '/',
                'domain'   => COOKIE_DOMAIN ?: '',
                'secure'   => is_ssl(),
                'httponly' => false, // JS moet kunnen lezen voor pre-fill
                'samesite' => 'Lax',
            ]
        );

        // Direct in $_COOKIE zodat dezelfde request al kan lezen
        $_COOKIE[ self::naam() ] = $token;
    }

    public static function wis(): void {
        if ( headers_sent() ) {
            return;
        }
        setcookie(
            self::naam(),
            '',
            [
                'expires'  => time() - 3600,
                'path'     => COOKIEPATH ?: '/',
                'domain'   => COOKIE_DOMAIN ?: '',
                'secure'   => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );
        unset( $_COOKIE[ self::naam() ] );
    }

    /**
     * Eenvoudige consent-check. Default: geen consent vereist.
     * Kan gefilterd worden door cookie consent plugins via filter 'psfid_has_consent'.
     */
    public static function heeft_consent(): bool {
        return (bool) apply_filters( 'psfid_has_consent', false );
    }
}
