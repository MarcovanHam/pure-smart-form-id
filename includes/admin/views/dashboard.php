<?php
defined( 'ABSPATH' ) || exit;

$stats    = PSFID_Store::statistieken();
$branding = PSFID_Plugin::get_branding();
$s        = PSFID_Plugin::get_settings();
$param    = $s['url_param'] ?? 'id';
$field    = $s['token_field_name'] ?? 'form_token';
?>
<div class="wrap psfid-wrap">
    <h1><?php echo esc_html( $branding['plugin_name'] ?: 'Pure Smart Form ID' ); ?></h1>

    <?php if ( isset( $_GET['cleaned'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %d: number of removed tokens */
                    esc_html__( 'Cleanup completed. Tokens removed: %d', 'pure-smart-form-id' ),
                    (int) $_GET['cleaned']
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['deleted_all'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %d: number of deleted tokens */
                    esc_html__( 'All tokens deleted: %d records erased.', 'pure-smart-form-id' ),
                    (int) $_GET['deleted_all']
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( ! PSFID_Plugin::fluent_forms_actief() && ! PSFID_Plugin::gravity_forms_actief() ) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e( 'Warning:', 'pure-smart-form-id' ); ?></strong>
                <?php esc_html_e( 'Neither Fluent Forms nor Gravity Forms is active. Activate at least one to use the plugin.', 'pure-smart-form-id' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="psfid-stats">
        <div class="psfid-stat-card">
            <span class="psfid-stat-num"><?php echo esc_html( number_format_i18n( $stats['totaal'] ) ); ?></span>
            <span class="psfid-stat-label"><?php esc_html_e( 'Total tokens', 'pure-smart-form-id' ); ?></span>
        </div>
        <div class="psfid-stat-card">
            <span class="psfid-stat-num"><?php echo esc_html( number_format_i18n( $stats['actief'] ) ); ?></span>
            <span class="psfid-stat-label"><?php esc_html_e( 'Active (not expired)', 'pure-smart-form-id' ); ?></span>
        </div>
        <div class="psfid-stat-card">
            <span class="psfid-stat-num"><?php echo esc_html( number_format_i18n( $stats['laatste_24u'] ) ); ?></span>
            <span class="psfid-stat-label"><?php esc_html_e( 'Updated in 24h', 'pure-smart-form-id' ); ?></span>
        </div>
        <div class="psfid-stat-card">
            <span class="psfid-stat-num"><?php echo esc_html( number_format_i18n( $stats['laatste_7d'] ) ); ?></span>
            <span class="psfid-stat-label"><?php esc_html_e( 'Updated in 7 days', 'pure-smart-form-id' ); ?></span>
        </div>
        <div class="psfid-stat-card">
            <span class="psfid-stat-num"><?php echo esc_html( number_format_i18n( $stats['verlopen'] ) ); ?></span>
            <span class="psfid-stat-label"><?php esc_html_e( 'Expired', 'pure-smart-form-id' ); ?></span>
        </div>
    </div>

    <div class="psfid-grid">
        <div class="psfid-card">
            <h2 style="margin-top:0"><?php esc_html_e( 'Quick actions', 'pure-smart-form-id' ); ?></h2>
            <p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=psfid-tokens' ) ); ?>"><?php esc_html_e( 'View all tokens', 'pure-smart-form-id' ); ?></a></p>
            <p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?psfid_export=all' ), 'psfid_export' ) ); ?>"><?php esc_html_e( 'Download all data (CSV)', 'pure-smart-form-id' ); ?></a></p>
            <p>
                <form method="post" style="display:inline">
                    <?php wp_nonce_field( 'psfid_run_cleanup' ); ?>
                    <input type="hidden" name="psfid_action" value="run_cleanup">
                    <button class="button" type="submit" onclick="return confirm('<?php echo esc_js( __( 'Run cleanup now?', 'pure-smart-form-id' ) ); ?>');"><?php esc_html_e( 'Run cleanup now', 'pure-smart-form-id' ); ?></button>
                </form>
            </p>
            <hr>
            <p>
                <strong><?php esc_html_e( 'Test mode', 'pure-smart-form-id' ); ?></strong>
            </p>
            <p>
                <form method="post" style="display:inline">
                    <?php wp_nonce_field( 'psfid_delete_all' ); ?>
                    <input type="hidden" name="psfid_action" value="delete_all_tokens">
                    <button class="button button-link-delete" type="submit" onclick="return confirm('<?php echo esc_js( __( 'Delete ALL tokens permanently? This cannot be undone.', 'pure-smart-form-id' ) ); ?>') &amp;&amp; confirm('<?php echo esc_js( __( 'Are you really sure? All token data will be erased.', 'pure-smart-form-id' ) ); ?>');"><?php esc_html_e( 'Delete all tokens (test mode)', 'pure-smart-form-id' ); ?></button>
                </form>
            </p>
            <p class="description"><?php esc_html_e( 'Useful while testing without burning through email addresses. Asks for double confirmation.', 'pure-smart-form-id' ); ?></p>
        </div>

        <div class="psfid-card">
            <h2 style="margin-top:0"><?php esc_html_e( 'Quick start', 'pure-smart-form-id' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Activate Fluent Forms or Gravity Forms (or both).', 'pure-smart-form-id' ); ?></li>
                <li>
                    <?php
                    printf(
                        /* translators: %s: link to integrations page */
                        wp_kses_post( __( 'Go to %s and optionally choose a CRM.', 'pure-smart-form-id' ) ),
                        '<a href="' . esc_url( admin_url( 'admin.php?page=psfid-integrations' ) ) . '">' . esc_html__( 'Integrations', 'pure-smart-form-id' ) . '</a>'
                    );
                    ?>
                </li>
                <li>
                    <?php
                    printf(
                        /* translators: %s: hidden field name */
                        esc_html__( 'Map the hidden token field in your CRM. Field name: %s', 'pure-smart-form-id' ),
                        '<code>' . esc_html( $field ) . '</code>'
                    );
                    ?>
                </li>
                <li>
                    <?php
                    printf(
                        /* translators: %s: URL parameter format */
                        esc_html__( 'Send emails from your CRM with %s', 'pure-smart-form-id' ),
                        '<code>?' . esc_html( $param ) . '={{token}}</code>'
                    );
                    ?>
                </li>
                <li>
                    <?php
                    printf(
                        /* translators: %s: link to help page */
                        wp_kses_post( __( 'Check the %s for detailed examples.', 'pure-smart-form-id' ) ),
                        '<a href="' . esc_url( admin_url( 'admin.php?page=psfid-help' ) ) . '">' . esc_html__( 'Help page', 'pure-smart-form-id' ) . '</a>'
                    );
                    ?>
                </li>
            </ol>
        </div>
    </div>

    <?php if ( ! defined( 'PSFID_BRAND_HIDE_CREDITS' ) || ! PSFID_BRAND_HIDE_CREDITS ) : ?>
        <div class="psfid-about">
            <div class="psfid-about-main">
                <h3><?php esc_html_e( 'About this plugin', 'pure-smart-form-id' ); ?></h3>
                <p>
                    <?php
                    printf(
                        /* translators: 1: author link, 2: brand site link */
                        wp_kses_post( __( 'Pure Smart Form ID is built by %1$s &middot; %2$s', 'pure-smart-form-id' ) ),
                        '<a href="https://www.marcovanham.nl" target="_blank" rel="noopener"><strong>Marco van Ham</strong></a>',
                        '<a href="https://pureplugins.eu" target="_blank" rel="noopener"><strong>pureplugins.eu</strong></a>'
                    );
                    ?>
                </p>
                <p class="description">
                    <?php esc_html_e( 'More plugins are on the way. Check pureplugins.eu for updates.', 'pure-smart-form-id' ); ?>
                </p>
            </div>
            <div class="psfid-about-meta">
                <div>
                    <strong><?php esc_html_e( 'Version', 'pure-smart-form-id' ); ?></strong>
                    <span><?php echo esc_html( PSFID_VERSION ); ?></span>
                </div>
                <div>
                    <strong><?php esc_html_e( 'License', 'pure-smart-form-id' ); ?></strong>
                    <span>GPL-2.0-or-later</span>
                </div>
                <div>
                    <strong><?php esc_html_e( 'Support', 'pure-smart-form-id' ); ?></strong>
                    <span><a href="https://pureplugins.eu" target="_blank" rel="noopener">pureplugins.eu</a></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
