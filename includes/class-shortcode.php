<?php
/**
 * Shortcodes: tonen van velden met fallback.
 *
 * Voorbeelden:
 *   [psfid key="voornaam" fallback="beste klant"]
 *   [psfid key="email"]
 *   [psfid_token]
 *   [psfid_if key="voornaam"]Hallo {voornaam}![/psfid_if]
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Shortcode {

    public function __construct() {
        add_shortcode( 'psfid', [ $this, 'render_field' ] );
        add_shortcode( 'psfid_token', [ $this, 'render_token' ] );
        add_shortcode( 'psfid_if', [ $this, 'render_if' ] );
    }

    /**
     * [psfid key="voornaam" fallback="beste klant"]
     * Server-side rendert wat we al weten. Frontend JS overschrijft als
     * we via cookie/REST nieuwe data binnenhalen.
     */
    public function render_field( $atts ): string {
        $atts = shortcode_atts( [
            'key'      => '',
            'fallback' => '',
            'wrap'     => 'span',
        ], $atts, 'psfid' );

        $key  = sanitize_key( strtolower( $atts['key'] ) );
        $val  = $key ? PSFID_Resolver::get_field( $key ) : null;
        $tag  = sanitize_key( $atts['wrap'] ) ?: 'span';
        $text = $val !== null && $val !== '' ? $val : $atts['fallback'];

        return sprintf(
            '<%1$s class="psfid-field" data-psfid-key="%2$s" data-psfid-fallback="%3$s">%4$s</%1$s>',
            esc_attr( $tag ),
            esc_attr( $key ),
            esc_attr( $atts['fallback'] ),
            esc_html( $text )
        );
    }

    /**
     * [psfid_token]
     * Toont alleen de huidige token-string (handig voor debug of een hidden link).
     */
    public function render_token(): string {
        $token = PSFID_Resolver::get_current();
        return $token ? esc_html( $token->token ) : '';
    }

    /**
     * [psfid_if key="voornaam"]
     *   Welkom terug, {voornaam}!
     * [/psfid_if]
     *
     * Toont de inhoud alleen als de waarde bekend is. {key} placeholders
     * worden vervangen.
     */
    public function render_if( $atts, $content = null ): string {
        $atts = shortcode_atts( [
            'key' => '',
        ], $atts, 'psfid_if' );

        $key = sanitize_key( strtolower( $atts['key'] ) );
        $val = $key ? PSFID_Resolver::get_field( $key ) : null;

        if ( ! $val || ! $content ) {
            return '';
        }

        // Vervang {voornaam} etc met huidige waardes
        $content = preg_replace_callback(
            '/\{([a-z0-9_-]+)\}/i',
            function ( $m ) {
                $v = PSFID_Resolver::get_field( strtolower( $m[1] ) );
                return $v !== null ? esc_html( $v ) : $m[0];
            },
            do_shortcode( $content )
        );

        return '<span class="psfid-if" data-psfid-if-key="' . esc_attr( $key ) . '">' . $content . '</span>';
    }
}
