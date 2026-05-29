<?php
defined( 'ABSPATH' ) || exit;
$s       = (array) get_option( 'psfid_settings', [] );
$aliases = (array) ( $s['field_aliases'] ?? [] );
$allowed = (array) ( $s['allowed_fields'] ?? [] );
?>
<div class="wrap psfid-wrap">
    <h1><?php esc_html_e( 'Fields', 'pure-smart-form-id' ); ?></h1>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Fields saved.', 'pure-smart-form-id' ); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'psfid_save_fields' ); ?>
        <input type="hidden" name="psfid_action" value="save_fields">

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'Field aliases', 'pure-smart-form-id' ); ?></h2>
                <p>
                    <?php
                    printf(
                        /* translators: 1: example alias, 2: example real key */
                        wp_kses_post( __( 'Translate localized field names to standard keys. For example: %1$s &rarr; %2$s.', 'pure-smart-form-id' ) ),
                        '<code>voornaam</code>',
                        '<code>first_name</code>'
                    );
                    ?>
                </p>
            </div>
            <div class="psfid-section-body">
                <table class="widefat" id="psfid-aliases">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Alias (form field name)', 'pure-smart-form-id' ); ?></th>
                            <th>&nbsp;</th>
                            <th><?php esc_html_e( 'Standard key', 'pure-smart-form-id' ); ?></th>
                            <th>&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 0;
                        foreach ( $aliases as $alias => $real ) : ?>
                            <tr>
                                <td><input type="text" name="aliases[<?php echo $i; ?>][alias]" value="<?php echo esc_attr( $alias ); ?>" class="regular-text"></td>
                                <td>&rarr;</td>
                                <td><input type="text" name="aliases[<?php echo $i; ?>][real]" value="<?php echo esc_attr( $real ); ?>" class="regular-text"></td>
                                <td><button type="button" class="button psfid-remove-row"><?php esc_html_e( 'Remove', 'pure-smart-form-id' ); ?></button></td>
                            </tr>
                        <?php $i++; endforeach; ?>
                        <tr>
                            <td><input type="text" name="aliases[<?php echo $i; ?>][alias]" value="" placeholder="<?php esc_attr_e( 'e.g. mailadres', 'pure-smart-form-id' ); ?>" class="regular-text"></td>
                            <td>&rarr;</td>
                            <td><input type="text" name="aliases[<?php echo $i; ?>][real]" value="" placeholder="<?php esc_attr_e( 'e.g. email', 'pure-smart-form-id' ); ?>" class="regular-text"></td>
                            <td><button type="button" class="button psfid-add-row" data-target="#psfid-aliases">+ <?php esc_html_e( 'Add row', 'pure-smart-form-id' ); ?></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'Whitelist (optional)', 'pure-smart-form-id' ); ?></h2>
                <p><?php esc_html_e( 'If you list field names here (one per line), only those fields will be stored. Leave empty to store all fields.', 'pure-smart-form-id' ); ?></p>
            </div>
            <div class="psfid-section-body">
                <textarea name="allowed_fields" rows="8" class="large-text code"><?php echo esc_textarea( implode( "\n", $allowed ) ); ?></textarea>
            </div>
        </div>

        <div class="psfid-submit-row">
            <?php submit_button( __( 'Save fields', 'pure-smart-form-id' ), 'primary', 'submit', false ); ?>
        </div>
    </form>
</div>
