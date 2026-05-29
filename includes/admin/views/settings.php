<?php
defined( 'ABSPATH' ) || exit;
$s = (array) get_option( 'psfid_settings', [] );
?>
<div class="wrap psfid-wrap">
    <h1><?php esc_html_e( 'Settings', 'pure-smart-form-id' ); ?></h1>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'pure-smart-form-id' ); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'psfid_save_settings' ); ?>
        <input type="hidden" name="psfid_action" value="save_settings">

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'URL and cookie', 'pure-smart-form-id' ); ?></h2>
                <p><?php esc_html_e( 'How visitors are recognized in URLs and via cookies.', 'pure-smart-form-id' ); ?></p>
            </div>
            <div class="psfid-section-body">
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'URL parameter', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <input type="text" name="url_param" value="<?php echo esc_attr( $s['url_param'] ?? 'id' ); ?>" class="regular-text">
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: 1: example parameter, 2: example URL, 3: default value */
                                    wp_kses_post( __( 'Query string parameter name. For example %1$s in %2$s. Default: %3$s.', 'pure-smart-form-id' ) ),
                                    '<code>id</code>',
                                    '<code>?id=token</code>',
                                    '<code>id</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Cookie name', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <input type="text" name="cookie_name" value="<?php echo esc_attr( $s['cookie_name'] ?? 'psfid_token' ); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Cookie lifetime (days)', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <input type="number" name="cookie_lifetime_days" min="1" max="3650" value="<?php echo (int) ( $s['cookie_lifetime_days'] ?? 30 ); ?>">
                            <p class="description"><?php esc_html_e( 'How long the cookie stays valid on the visitor\'s device. Default 30 days.', 'pure-smart-form-id' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Cookie consent required', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="cookie_consent_required" value="1" <?php checked( ! empty( $s['cookie_consent_required'] ) ); ?>> <?php esc_html_e( 'Only set cookie after explicit consent', 'pure-smart-form-id' ); ?></label>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: filter name */
                                    wp_kses_post( __( 'Enable if you use a cookie banner. Other plugins can signal consent via the %s filter.', 'pure-smart-form-id' ) ),
                                    '<code>psfid_has_consent</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'Retention periods', 'pure-smart-form-id' ); ?></h2>
                <p><?php esc_html_e( 'How long tokens, data and logs are kept.', 'pure-smart-form-id' ); ?></p>
            </div>
            <div class="psfid-section-body">
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Token expiry (days)', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <input type="number" name="token_expiry_days" min="1" max="3650" value="<?php echo (int) ( $s['token_expiry_days'] ?? 365 ); ?>">
                            <p class="description"><?php esc_html_e( 'How long a token in a URL stays valid. Extended on every new activity.', 'pure-smart-form-id' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Data retention (days)', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <input type="number" name="data_retention_days" min="1" max="3650" value="<?php echo (int) ( $s['data_retention_days'] ?? 90 ); ?>">
                            <p class="description"><?php esc_html_e( 'Tokens without activity are automatically deleted after X days. Default 90 days.', 'pure-smart-form-id' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Log retention (days)', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <input type="number" name="log_retention_days" min="0" max="3650" value="<?php echo (int) ( $s['log_retention_days'] ?? 30 ); ?>">
                            <p class="description"><?php esc_html_e( 'Audit log entries older than X days are removed. 0 = never clean up.', 'pure-smart-form-id' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'Token field', 'pure-smart-form-id' ); ?></h2>
                <p><?php esc_html_e( 'The hidden field that the plugin injects into your forms.', 'pure-smart-form-id' ); ?></p>
            </div>
            <div class="psfid-section-body">
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Hidden field name', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <input type="text" name="token_field_name" value="<?php echo esc_attr( $s['token_field_name'] ?? 'form_token' ); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e( 'Name of the hidden field injected into forms. Use this in your CRM mapping.', 'pure-smart-form-id' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Auto-inject token field', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="auto_inject_token" value="1" <?php checked( ! empty( $s['auto_inject_token'] ) ); ?>> <?php esc_html_e( 'Automatically add a hidden token field to every form', 'pure-smart-form-id' ); ?></label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'Identification fallbacks', 'pure-smart-form-id' ); ?></h2>
                <p><?php esc_html_e( 'When a visitor has no token in URL or cookie, try to recognize them via these signals.', 'pure-smart-form-id' ); ?></p>
            </div>
            <div class="psfid-section-body">
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Auto-link logged-in WordPress users', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <?php $auto_link_default = ! isset( $s['auto_link_wp_user'] ) ? true : (bool) $s['auto_link_wp_user']; ?>
                            <label><input type="checkbox" name="auto_link_wp_user" value="1" <?php checked( $auto_link_default ); ?>> <?php esc_html_e( 'Identify logged-in WordPress users via their account email', 'pure-smart-form-id' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Existing tokens are reused. If a logged-in user has no token yet, a new one is created automatically (source: wp_user_auto). Turn this off if you do not want tokens for non-form visitors (admins, customers, forum members).', 'pure-smart-form-id' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Email match fallback', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="email_lookup_enabled" value="1" <?php checked( ! empty( $s['email_lookup_enabled'] ) ); ?>> <?php esc_html_e( 'Identify visitors without a token using their email address', 'pure-smart-form-id' ); ?></label>
                            <p class="description"><?php esc_html_e( 'Prevents duplicate records when the user clears cookies or uses another device.', 'pure-smart-form-id' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Phone match fallback', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="phone_lookup_enabled" value="1" <?php checked( ! empty( $s['phone_lookup_enabled'] ) ); ?>> <?php esc_html_e( 'Identify visitors without a token using their phone number', 'pure-smart-form-id' ); ?></label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if ( function_exists( 'FluentCrmApi' ) ) : ?>
        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'FluentCRM data source', 'pure-smart-form-id' ); ?></h2>
                <p><?php esc_html_e( 'When FluentCRM is active, contact fields and custom_values from FluentCRM can be merged into PSFID resolver fields. Forms then pre-fill with the latest CRM data automatically.', 'pure-smart-form-id' ); ?></p>
            </div>
            <div class="psfid-section-body">
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Merge FluentCRM data', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <?php $fc_default = ! isset( $s['fc_source_enabled'] ) ? true : (bool) $s['fc_source_enabled']; ?>
                            <label><input type="checkbox" name="fc_source_enabled" value="1" <?php checked( $fc_default ); ?>> <?php esc_html_e( 'Read contact fields and custom_values from FluentCRM into PSFID', 'pure-smart-form-id' ); ?></label>
                            <p class="description">
                                <?php esc_html_e( 'FluentCRM wins on conflicts: if both PSFID and FluentCRM have a value for the same field, the FluentCRM value is used. Empty FluentCRM values never overwrite PSFID data.', 'pure-smart-form-id' ); ?>
                                <br>
                                <?php
                                printf(
                                    /* translators: %s: filter name */
                                    wp_kses_post( __( 'Developers: restrict which fields are merged via the %s filter.', 'pure-smart-form-id' ) ),
                                    '<code>psfid_fc_source_fields</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'FluentCRM contact recognition via URL', 'pure-smart-form-id' ); ?></h2>
                <p><?php esc_html_e( 'Recognize a visitor when a URL contains the value of a FluentCRM custom field. Useful for existing email links like ?rid=VALUE that point to a FluentCRM contact field. No data in FluentCRM is changed.', 'pure-smart-form-id' ); ?></p>
            </div>
            <div class="psfid-section-body">
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Enable URL recognition', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="fc_url_lookup_enabled" value="1" <?php checked( ! empty( $s['fc_url_lookup_enabled'] ) ); ?>> <?php esc_html_e( 'Resolve a URL parameter against a FluentCRM custom field', 'pure-smart-form-id' ); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'URL parameter', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <input type="text" name="fc_url_param" value="<?php echo esc_attr( $s['fc_url_param'] ?? 'rid' ); ?>" class="regular-text" placeholder="rid">
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: example URL */
                                    wp_kses_post( __( 'The query parameter that carries the value. For example %s.', 'pure-smart-form-id' ) ),
                                    '<code>?rid=VALUE</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'FluentCRM custom field slug', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <input type="text" name="fc_url_field" value="<?php echo esc_attr( $s['fc_url_field'] ?? '' ); ?>" class="regular-text" placeholder="random_userid">
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: 1: example smartcode, 2: example slug */
                                    wp_kses_post( __( 'The slug of the FluentCRM custom field to match against. If your emails use %1$s, the slug is %2$s.', 'pure-smart-form-id' ) ),
                                    '<code>{{contact.custom.random_userid}}</code>',
                                    '<code>random_userid</code>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'Privacy', 'pure-smart-form-id' ); ?></h2>
            </div>
            <div class="psfid-section-body">
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Delete on uninstall', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( ! empty( $s['delete_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Erase all data when the plugin is uninstalled', 'pure-smart-form-id' ); ?></label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="psfid-submit-row">
            <?php submit_button( __( 'Save settings', 'pure-smart-form-id' ), 'primary', 'submit', false ); ?>
        </div>
    </form>
</div>
