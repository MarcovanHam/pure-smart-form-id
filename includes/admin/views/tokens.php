<?php
defined( 'ABSPATH' ) || exit;

$search   = sanitize_text_field( $_GET['s'] ?? '' );
$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
$per_page = 25;
$offset   = ( $paged - 1 ) * $per_page;

$detail_id = (int) ( $_GET['view'] ?? 0 );
?>
<div class="wrap psfid-wrap">
    <h1><?php esc_html_e( 'Tokens', 'pure-smart-form-id' ); ?>
        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?psfid_export=all' ), 'psfid_export' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Export all (CSV)', 'pure-smart-form-id' ); ?></a>
    </h1>

    <?php if ( $detail_id ) :
        $rec = PSFID_Store::haal_volledig_record_op( $detail_id );
        if ( $rec ) : ?>

            <div class="psfid-section">
                <div class="psfid-section-header">
                    <h2><?php esc_html_e( 'Token:', 'pure-smart-form-id' ); ?> <code><?php echo esc_html( $rec['token'] ); ?></code></h2>
                </div>
                <div class="psfid-section-body">
                    <p style="margin-top:0">
                        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=psfid-tokens' ) ); ?>"><?php esc_html_e( 'Back to overview', 'pure-smart-form-id' ); ?></a>
                        <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?psfid_export=single&token_id=' . $detail_id ), 'psfid_export' ) ); ?>"><?php esc_html_e( 'Download as CSV', 'pure-smart-form-id' ); ?></a>
                        <button type="button" class="button button-link-delete psfid-delete-token" data-token-id="<?php echo (int) $detail_id; ?>"><?php esc_html_e( 'Delete this token', 'pure-smart-form-id' ); ?></button>
                    </p>
                    <table class="widefat striped" style="margin-top:16px">
                        <tbody>
                            <tr><th style="width:200px"><?php esc_html_e( 'Created', 'pure-smart-form-id' ); ?></th><td><?php echo esc_html( $rec['created_at'] ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Last updated', 'pure-smart-form-id' ); ?></th><td><?php echo esc_html( $rec['updated_at'] ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Expires', 'pure-smart-form-id' ); ?></th><td><?php echo esc_html( $rec['expires_at'] ?: __( 'never', 'pure-smart-form-id' ) ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Source', 'pure-smart-form-id' ); ?></th><td><?php echo esc_html( $rec['source'] ?: '-' ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Email', 'pure-smart-form-id' ); ?></th><td><?php echo esc_html( $rec['email'] ?: '-' ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'Phone', 'pure-smart-form-id' ); ?></th><td><?php echo esc_html( $rec['phone'] ?: '-' ); ?></td></tr>
                            <tr><th><?php esc_html_e( 'CRM synced', 'pure-smart-form-id' ); ?></th><td>
                                <?php if ( $rec['crm_synced'] ) : ?>
                                    <span class="psfid-pill psfid-pill-success"><?php esc_html_e( 'Yes', 'pure-smart-form-id' ); ?></span>
                                <?php else : ?>
                                    <span class="psfid-pill psfid-pill-muted"><?php esc_html_e( 'No', 'pure-smart-form-id' ); ?></span>
                                <?php endif; ?>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="psfid-section">
                <div class="psfid-section-header">
                    <h2><?php
                    printf(
                        /* translators: %d: number of fields */
                        esc_html__( 'Fields (%d)', 'pure-smart-form-id' ),
                        count( $rec['fields'] )
                    );
                    ?></h2>
                </div>
                <div class="psfid-section-body">
                    <table class="widefat striped">
                        <thead><tr><th style="width:30%"><?php esc_html_e( 'Field', 'pure-smart-form-id' ); ?></th><th><?php esc_html_e( 'Value', 'pure-smart-form-id' ); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ( $rec['fields'] as $k => $v ) : ?>
                                <tr><td><code><?php echo esc_html( $k ); ?></code></td><td><?php echo esc_html( $v ); ?></td></tr>
                            <?php endforeach; ?>
                            <?php if ( empty( $rec['fields'] ) ) : ?>
                                <tr><td colspan="2"><em><?php esc_html_e( 'No fields stored.', 'pure-smart-form-id' ); ?></em></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php
            // Toon GF Save & Continue drafts voor deze email (debug-info voor multi-page resume)
            global $wpdb;
            $gf_drafts = [];
            if ( ! empty( $rec['email'] ) ) {
                $gf_table = $wpdb->prefix . 'gf_draft_submissions';
                if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $gf_table ) ) === $gf_table ) {
                    $email_lower = strtolower( trim( $rec['email'] ) );
                    $gf_drafts = $wpdb->get_results( $wpdb->prepare(
                        "SELECT uuid, form_id, email, date_created FROM $gf_table
                         WHERE LOWER(email) = %s
                         ORDER BY date_created DESC LIMIT 10",
                        $email_lower
                    ) );
                }
            }
            if ( ! empty( $gf_drafts ) ) : ?>
                <div class="psfid-section">
                    <div class="psfid-section-header">
                        <h2><?php esc_html_e( 'Gravity Forms drafts (Save &amp; Continue)', 'pure-smart-form-id' ); ?></h2>
                        <p><?php esc_html_e( 'Drafts van dit email-adres in de Gravity Forms tabel. Bij PSFID-link met deze token redirect de plugin automatisch naar de juiste pagina via de uuid.', 'pure-smart-form-id' ); ?></p>
                    </div>
                    <div class="psfid-section-body">
                        <table class="widefat striped">
                            <thead><tr>
                                <th><?php esc_html_e( 'Form ID', 'pure-smart-form-id' ); ?></th>
                                <th><?php esc_html_e( 'Resume token (uuid)', 'pure-smart-form-id' ); ?></th>
                                <th><?php esc_html_e( 'Email (in draft)', 'pure-smart-form-id' ); ?></th>
                                <th><?php esc_html_e( 'Created', 'pure-smart-form-id' ); ?></th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ( $gf_drafts as $d ) : ?>
                                    <tr>
                                        <td><?php echo (int) $d->form_id; ?></td>
                                        <td><code style="font-size:11px"><?php echo esc_html( $d->uuid ); ?></code></td>
                                        <td><?php echo esc_html( $d->email ); ?></td>
                                        <td><?php echo esc_html( $d->date_created ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="psfid-section">
                <div class="psfid-section-header">
                    <h2><?php esc_html_e( 'Audit log', 'pure-smart-form-id' ); ?></h2>
                </div>
                <div class="psfid-section-body">
                    <table class="widefat striped">
                        <thead><tr>
                            <th style="width:180px"><?php esc_html_e( 'Time', 'pure-smart-form-id' ); ?></th>
                            <th style="width:140px"><?php esc_html_e( 'Action', 'pure-smart-form-id' ); ?></th>
                            <th><?php esc_html_e( 'Details', 'pure-smart-form-id' ); ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ( PSFID_Log::haal_op( $detail_id, 50 ) as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $row->created_at ); ?></td>
                                    <td><code><?php echo esc_html( $row->action ); ?></code></td>
                                    <td><?php echo esc_html( $row->details ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else : ?>
            <div class="psfid-section">
                <div class="psfid-section-body">
                    <p><?php esc_html_e( 'Token not found.', 'pure-smart-form-id' ); ?></p>
                </div>
            </div>
        <?php endif;
    else :
        $totaal = PSFID_Store::tel_tokens( $search );
        $tokens = PSFID_Store::haal_alle_tokens( [
            'limit'  => $per_page,
            'offset' => $offset,
            'search' => $search,
        ] );
    ?>
        <div class="psfid-section">
            <div class="psfid-section-body">
                <form method="get" style="margin:0">
                    <input type="hidden" name="page" value="psfid-tokens">
                    <p class="search-box" style="margin:0 0 12px">
                        <label class="screen-reader-text" for="psfid-search"><?php esc_html_e( 'Search tokens:', 'pure-smart-form-id' ); ?></label>
                        <input type="search" id="psfid-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by token, email or phone...', 'pure-smart-form-id' ); ?>" style="min-width:300px">
                        <input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'pure-smart-form-id' ); ?>">
                    </p>
                </form>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Token', 'pure-smart-form-id' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'pure-smart-form-id' ); ?></th>
                            <th><?php esc_html_e( 'Phone', 'pure-smart-form-id' ); ?></th>
                            <th><?php esc_html_e( 'Source', 'pure-smart-form-id' ); ?></th>
                            <th><?php esc_html_e( 'Updated', 'pure-smart-form-id' ); ?></th>
                            <th><?php esc_html_e( 'CRM', 'pure-smart-form-id' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'pure-smart-form-id' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $tokens ) ) : ?>
                            <tr><td colspan="7"><em><?php esc_html_e( 'No tokens yet. Submit a form to create one.', 'pure-smart-form-id' ); ?></em></td></tr>
                        <?php else :
                            foreach ( $tokens as $t ) : ?>
                                <tr>
                                    <td><code><?php echo esc_html( $t->token ); ?></code></td>
                                    <td><?php echo esc_html( $t->email ?: '-' ); ?></td>
                                    <td><?php echo esc_html( $t->phone ?: '-' ); ?></td>
                                    <td><?php echo esc_html( $t->source ?: '-' ); ?></td>
                                    <td><?php echo esc_html( $t->updated_at ); ?></td>
                                    <td>
                                        <?php if ( $t->crm_synced ) : ?>
                                            <span class="psfid-pill psfid-pill-success"><?php esc_html_e( 'Yes', 'pure-smart-form-id' ); ?></span>
                                        <?php else : ?>
                                            <span class="psfid-pill psfid-pill-muted"><?php esc_html_e( 'No', 'pure-smart-form-id' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=psfid-tokens&view=' . $t->id ) ); ?>"><?php esc_html_e( 'Details', 'pure-smart-form-id' ); ?></a>
                                        |
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?psfid_export=single&token_id=' . $t->id ), 'psfid_export' ) ); ?>"><?php esc_html_e( 'CSV', 'pure-smart-form-id' ); ?></a>
                                        |
                                        <a href="#" class="psfid-delete-token" data-token-id="<?php echo (int) $t->id; ?>" style="color:#b32d2e"><?php esc_html_e( 'Delete', 'pure-smart-form-id' ); ?></a>
                                    </td>
                                </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php
                $aantal_paginas = (int) ceil( $totaal / $per_page );
                if ( $aantal_paginas > 1 ) :
                    $base_url = admin_url( 'admin.php?page=psfid-tokens' );
                    if ( $search ) {
                        $base_url = add_query_arg( 's', $search, $base_url );
                    }
                    ?>
                    <div class="tablenav" style="margin-top:12px"><div class="tablenav-pages">
                        <?php
                        echo paginate_links( [
                            'base'    => add_query_arg( 'paged', '%#%', $base_url ),
                            'format'  => '',
                            'current' => $paged,
                            'total'   => $aantal_paginas,
                        ] );
                        ?>
                    </div></div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>
