<?php
/**
 * WordPress hoofd-dashboard widget.
 * Toont status van Pure Smart Form ID zodra een admin inlogt.
 */
defined( 'ABSPATH' ) || exit;

$stats   = PSFID_Store::statistieken();
$recente = PSFID_Store::recente_tokens( 5 );
?>
<div class="psfid-wp-widget">
    <div class="psfid-wp-widget-stats">
        <div class="psfid-wp-stat">
            <span class="psfid-wp-stat-num"><?php echo esc_html( number_format_i18n( $stats['totaal'] ) ); ?></span>
            <span class="psfid-wp-stat-lbl"><?php esc_html_e( 'Total', 'pure-smart-form-id' ); ?></span>
        </div>
        <div class="psfid-wp-stat">
            <span class="psfid-wp-stat-num"><?php echo esc_html( number_format_i18n( $stats['actief'] ) ); ?></span>
            <span class="psfid-wp-stat-lbl"><?php esc_html_e( 'Active', 'pure-smart-form-id' ); ?></span>
        </div>
        <div class="psfid-wp-stat">
            <span class="psfid-wp-stat-num"><?php echo esc_html( number_format_i18n( $stats['laatste_24u'] ) ); ?></span>
            <span class="psfid-wp-stat-lbl"><?php esc_html_e( 'Last 24h', 'pure-smart-form-id' ); ?></span>
        </div>
    </div>

    <?php if ( ! empty( $recente ) ) : ?>
        <h3 class="psfid-wp-widget-h"><?php esc_html_e( 'Recent activity', 'pure-smart-form-id' ); ?></h3>
        <ul class="psfid-wp-widget-list">
            <?php foreach ( $recente as $rec ) :
                $masked = $rec->email ? PSFID_Store::mask_email( $rec->email ) : '<code style="font-size:11px">' . esc_html( $rec->token ) . '</code>';
                $time   = human_time_diff( strtotime( $rec->updated_at ), current_time( 'timestamp' ) );
                ?>
                <li>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=psfid-tokens&view=' . (int) $rec->id ) ); ?>">
                        <?php echo wp_kses_post( $masked ); ?>
                    </a>
                    <span class="psfid-wp-widget-time">
                        <?php
                        printf(
                            /* translators: %s: human readable time difference */
                            esc_html__( '%s ago', 'pure-smart-form-id' ),
                            esc_html( $time )
                        );
                        ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <p class="psfid-wp-widget-empty">
            <?php esc_html_e( 'No tokens captured yet. Submit a form to see activity here.', 'pure-smart-form-id' ); ?>
        </p>
    <?php endif; ?>

    <div class="psfid-wp-widget-actions">
        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=psfid-dashboard' ) ); ?>">
            <?php esc_html_e( 'Open dashboard', 'pure-smart-form-id' ); ?>
        </a>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=psfid-tokens' ) ); ?>">
            <?php esc_html_e( 'All tokens', 'pure-smart-form-id' ); ?>
        </a>
    </div>

    <?php if ( ! defined( 'PSFID_BRAND_HIDE_CREDITS' ) || ! PSFID_BRAND_HIDE_CREDITS ) : ?>
        <div class="psfid-wp-widget-credit">
            <?php
            printf(
                /* translators: 1: author name link, 2: brand site link */
                wp_kses_post( __( 'Made by %1$s &middot; %2$s', 'pure-smart-form-id' ) ),
                '<a href="https://www.marcovanham.nl" target="_blank" rel="noopener">Marco van Ham</a>',
                '<a href="https://pureplugins.eu" target="_blank" rel="noopener">pureplugins.eu</a>'
            );
            ?>
        </div>
    <?php endif; ?>
</div>
