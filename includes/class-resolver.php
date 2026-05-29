<?php
/**
 * Resolver: bij elke pageload de bezoeker matchen aan een bestaand token
 * en de bekende data klaarzetten voor frontend + shortcodes.
 */

defined( 'ABSPATH' ) || exit;

class PSFID_Resolver {

    private static ?object $current_token = null;
    private static array $current_fields  = [];

    public function __construct() {
        // Vroeg in de request resolven (na init, voor template_redirect)
        add_action( 'wp_loaded', [ $this, 'resolve' ], 5 );
    }

    public function resolve(): void {
        // Skip in admin / AJAX / REST
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $instellingen = PSFID_Plugin::get_settings();

        // Stap 1: token uit URL parameter
        $url_param = $instellingen['url_param'] ?? 'id';
        $token_str = '';

        if ( ! empty( $_GET[ $url_param ] ) ) {
            $token_str = sanitize_text_field( wp_unslash( $_GET[ $url_param ] ) );
            $rec       = PSFID_Token::zoek_op_token( $token_str );
            if ( $rec ) {
                self::set_current( $rec );
                // Cookie verversen zodat klant na URL-bezoek herkend blijft
                PSFID_Cookie::zet( $rec->token );
                PSFID_Log::schrijf( (int) $rec->id, 'resolved_url' );
                return;
            }
        }

        // Stap 1b: FluentCRM contact via custom-field URL-parameter (bijv. ?rid=).
        // Live resolve: de URL bevat de waarde van een FC custom field (bijv.
        // random_userid). Wij zoeken het contact erbij, pakken de email en koppelen
        // een PSFID-token. Hiermee blijven oude FluentCRM-mailings werken zonder
        // dat er iets in FluentCRM gewijzigd hoeft te worden.
        if ( ! empty( $instellingen['fc_url_lookup_enabled'] )
            && class_exists( 'PSFID_FC_Source' )
            && PSFID_FC_Source::is_enabled()
        ) {
            $rid_param = $instellingen['fc_url_param'] ?? 'rid';
            $fc_slug   = $instellingen['fc_url_field'] ?? '';

            if ( $rid_param && $fc_slug && ! empty( $_GET[ $rid_param ] ) ) {
                $rid_val = sanitize_text_field( wp_unslash( $_GET[ $rid_param ] ) );
                $email   = PSFID_FC_Source::vind_email_op_custom_field( $fc_slug, $rid_val );

                if ( $email ) {
                    $rec = PSFID_Token::zoek_op_email( $email );
                    if ( ! $rec ) {
                        $nieuw_id = PSFID_Token::maak_record( $email, null, 'fc_url_rid' );
                        $rec      = PSFID_Token::zoek_op_id( $nieuw_id );
                    }
                    if ( $rec ) {
                        self::set_current( $rec );
                        PSFID_Cookie::zet( $rec->token );
                        PSFID_Log::schrijf( (int) $rec->id, 'resolved_fc_rid', $rid_val );
                        return;
                    }
                }
            }
        }

        // Stap 2: token uit cookie
        $cookie_token = PSFID_Cookie::lees();
        if ( $cookie_token ) {
            $rec = PSFID_Token::zoek_op_token( $cookie_token );
            if ( $rec ) {
                self::set_current( $rec );
                return;
            } else {
                // Cookie verwijst naar verlopen/verwijderd token: cookie wissen
                PSFID_Cookie::wis();
            }
        }

        // Stap 3: ingelogde WP-gebruiker via email-match
        // (default aan, uit te zetten via setting auto_link_wp_user)
        $wp_user_enabled = ! isset( $instellingen['auto_link_wp_user'] )
            ? true
            : (bool) $instellingen['auto_link_wp_user'];

        if ( $wp_user_enabled && is_user_logged_in() ) {
            $user  = wp_get_current_user();
            $email = $user && ! empty( $user->user_email ) ? sanitize_email( $user->user_email ) : '';

            if ( $email && is_email( $email ) ) {
                $rec = PSFID_Token::zoek_op_email( $email );

                if ( ! $rec ) {
                    // Geen bestaand token voor deze WP-user: maak er een aan
                    // zodat FC-source-merge en form pre-fill kunnen werken.
                    // Source 'wp_user_auto' zodat je dit later kan filteren.
                    $nieuw_id = PSFID_Token::maak_record( $email, null, 'wp_user_auto' );
                    $rec      = PSFID_Token::zoek_op_id( $nieuw_id );
                }

                if ( $rec ) {
                    self::set_current( $rec );
                    PSFID_Cookie::zet( $rec->token );
                    PSFID_Log::schrijf( (int) $rec->id, 'resolved_wp_user', $email );
                    return;
                }
            }
        }

        // Geen match - geen actie. Email/phone fallback gebeurt bij form submit.
    }

    public static function set_current( object $token_record ): void {
        self::$current_token  = $token_record;
        self::$current_fields = PSFID_Store::haal_velden_op( (int) $token_record->id );

        // Voeg email/phone toe vanuit token-record als ze niet in fields zitten
        if ( $token_record->email && empty( self::$current_fields['email'] ) ) {
            self::$current_fields['email'] = $token_record->email;
        }
        if ( $token_record->phone && empty( self::$current_fields['phone'] ) ) {
            self::$current_fields['phone'] = $token_record->phone;
        }

        // FluentCRM-as-source: mergen vóór aliassen, zodat ook FC-velden via aliassen
        // benaderbaar zijn. FC wint bij conflict (FC is autoritatief voor contact-data).
        if ( ! empty( $token_record->email ) && class_exists( 'PSFID_FC_Source' ) ) {
            $fc_velden = PSFID_FC_Source::haal_velden_op( (string) $token_record->email );
            foreach ( $fc_velden as $key => $val ) {
                self::$current_fields[ $key ] = $val;
            }
        }

        // Aliassen toepassen (zodat zowel 'voornaam' als 'first_name' werkt)
        $instellingen = PSFID_Plugin::get_settings();
        $aliases      = (array) ( $instellingen['field_aliases'] ?? [] );
        foreach ( $aliases as $alias => $real_key ) {
            if ( isset( self::$current_fields[ $real_key ] ) && ! isset( self::$current_fields[ $alias ] ) ) {
                self::$current_fields[ $alias ] = self::$current_fields[ $real_key ];
            }
        }
    }

    public static function get_current(): ?object {
        return self::$current_token;
    }

    public static function get_current_fields(): array {
        return self::$current_fields;
    }

    public static function get_field( string $key ): ?string {
        $key = strtolower( $key );
        return self::$current_fields[ $key ] ?? null;
    }

    public static function heeft_token(): bool {
        return self::$current_token !== null;
    }
}
