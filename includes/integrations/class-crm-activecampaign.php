<?php
/**
 * ActiveCampaign bridge.
 *
 * Vereist instellingen:
 * - ac_api_url (bijv. https://klantnaam.api-us1.com)
 * - ac_api_key
 * - ac_list_id (optioneel)
 * - ac_field_id (optioneel: ID van custom field om token in op te slaan)
 *
 * API documentatie: https://developers.activecampaign.com/reference/contact-sync
 */

defined( 'ABSPATH' ) || exit;

class PSFID_CRM_ActiveCampaign {

    public function __construct() {
        add_action( 'psfid/token_captured', [ $this, 'sync' ], 20, 5 );
    }

    public function sync( int $token_id, string $token, array $data, string $bron, $form_id ): void {
        $instellingen = PSFID_Plugin::get_settings();
        $api_url = trim( (string) ( $instellingen['ac_api_url'] ?? '' ) );
        $api_key = trim( (string) ( $instellingen['ac_api_key'] ?? '' ) );

        if ( ! $api_url || ! $api_key ) {
            return;
        }

        $email = $this->extract_email( $data );
        if ( ! $email ) {
            return;
        }

        try {
            $contact_payload = [
                'contact' => [
                    'email'     => $email,
                    'firstName' => $data['first_name'] ?? $data['voornaam'] ?? '',
                    'lastName'  => $data['last_name']  ?? $data['achternaam'] ?? '',
                    'phone'     => $data['phone']      ?? $data['telefoon'] ?? '',
                    'fieldValues' => [],
                ],
            ];

            // Custom field voor token (als field_id ingesteld)
            $field_id = (int) ( $instellingen['ac_field_id'] ?? 0 );
            if ( $field_id > 0 ) {
                $contact_payload['contact']['fieldValues'][] = [
                    'field' => $field_id,
                    'value' => $token,
                ];
            }

            // Lege keys eruit
            $contact_payload['contact'] = array_filter(
                $contact_payload['contact'],
                fn( $v ) => $v !== '' && $v !== null && $v !== []
            );

            // Sync (create or update)
            $response = $this->api_call(
                $api_url,
                $api_key,
                'POST',
                '/api/3/contact/sync',
                $contact_payload
            );

            $contact_id = $response['contact']['id'] ?? null;

            // Optioneel: aan lijst toevoegen
            $list_id = (int) ( $instellingen['ac_list_id'] ?? 0 );
            if ( $contact_id && $list_id > 0 ) {
                $this->api_call(
                    $api_url,
                    $api_key,
                    'POST',
                    '/api/3/contactLists',
                    [
                        'contactList' => [
                            'list'    => $list_id,
                            'contact' => $contact_id,
                            'status'  => 1, // 1 = actief
                        ],
                    ]
                );
            }

            // Markeer als gesynced
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'psfid_tokens',
                [ 'crm_synced' => 1 ],
                [ 'id' => $token_id ],
                [ '%d' ],
                [ '%d' ]
            );

            PSFID_Log::schrijf( $token_id, 'crm_synced', 'ActiveCampaign: ' . $email . ' (id=' . $contact_id . ')' );

        } catch ( \Throwable $e ) {
            PSFID_Log::schrijf( $token_id, 'crm_error', 'ActiveCampaign: ' . $e->getMessage() );
        }
    }

    /**
     * Test de API verbinding (vanuit instellingen scherm).
     */
    public static function test_verbinding( string $api_url, string $api_key ): array {
        $response = wp_remote_get( trailingslashit( $api_url ) . 'api/3/users/me', [
            'headers' => [
                'Api-Token' => $api_key,
                'Accept'    => 'application/json',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return [ 'success' => false, 'message' => 'HTTP ' . $code . ': ' . wp_remote_retrieve_body( $response ) ];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $email = $body['user']['email'] ?? 'onbekend';
        return [ 'success' => true, 'message' => 'Verbonden als ' . $email ];
    }

    /**
     * Haal beschikbare custom fields op (voor de instellingen dropdown).
     */
    public static function haal_custom_fields( string $api_url, string $api_key ): array {
        $response = wp_remote_get( trailingslashit( $api_url ) . 'api/3/fields?limit=100', [
            'headers' => [
                'Api-Token' => $api_key,
                'Accept'    => 'application/json',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $fields = $body['fields'] ?? [];

        $result = [];
        foreach ( $fields as $f ) {
            $result[ (int) $f['id'] ] = $f['title'] ?? ( 'Field ' . $f['id'] );
        }
        return $result;
    }

    /**
     * Haal beschikbare lijsten op (voor de instellingen dropdown).
     */
    public static function haal_lijsten( string $api_url, string $api_key ): array {
        $response = wp_remote_get( trailingslashit( $api_url ) . 'api/3/lists?limit=100', [
            'headers' => [
                'Api-Token' => $api_key,
                'Accept'    => 'application/json',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $lists = $body['lists'] ?? [];

        $result = [];
        foreach ( $lists as $l ) {
            $result[ (int) $l['id'] ] = $l['name'] ?? ( 'List ' . $l['id'] );
        }
        return $result;
    }

    /* ---------- Helpers ---------- */

    private function api_call( string $api_url, string $api_key, string $method, string $endpoint, array $body = [] ): array {
        $url = trailingslashit( $api_url ) . ltrim( $endpoint, '/' );
        $args = [
            'method'  => $method,
            'headers' => [
                'Api-Token'    => $api_key,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'timeout' => 20,
        ];
        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body_str = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            throw new \RuntimeException( 'AC API ' . $code . ': ' . $body_str );
        }

        return (array) json_decode( $body_str, true );
    }

    private function extract_email( array $data ): ?string {
        foreach ( [ 'email', 'email_address', 'mail' ] as $k ) {
            if ( ! empty( $data[ $k ] ) && is_email( $data[ $k ] ) ) {
                return sanitize_email( $data[ $k ] );
            }
        }
        return null;
    }
}
