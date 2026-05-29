<?php
defined( 'ABSPATH' ) || exit;
$s = (array) get_option( 'psfid_settings', [] );
?>
<div class="wrap psfid-wrap">
    <h1><?php esc_html_e( 'Integrations', 'pure-smart-form-id' ); ?></h1>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Integrations saved.', 'pure-smart-form-id' ); ?></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'psfid_save_integrations' ); ?>
        <input type="hidden" name="psfid_action" value="save_integrations">

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'Form plugins', 'pure-smart-form-id' ); ?></h2>
                <p><?php esc_html_e( 'Enable the form plugins you want this plugin to integrate with.', 'pure-smart-form-id' ); ?></p>
            </div>
            <div class="psfid-section-body">
                <table class="form-table">
                    <tr>
                        <th><label>Fluent Forms</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_fluent_forms" value="1" <?php checked( ! empty( $s['enable_fluent_forms'] ) ); ?>>
                                <?php esc_html_e( 'Enable', 'pure-smart-form-id' ); ?>
                            </label>
                            <?php if ( PSFID_Plugin::fluent_forms_actief() ) : ?>
                                <span class="psfid-pill psfid-pill-success"><?php esc_html_e( 'Plugin detected', 'pure-smart-form-id' ); ?></span>
                            <?php else : ?>
                                <span class="psfid-pill psfid-pill-muted"><?php esc_html_e( 'Plugin not active', 'pure-smart-form-id' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Gravity Forms</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_gravity_forms" value="1" <?php checked( ! empty( $s['enable_gravity_forms'] ) ); ?>>
                                <?php esc_html_e( 'Enable', 'pure-smart-form-id' ); ?>
                            </label>
                            <?php if ( PSFID_Plugin::gravity_forms_actief() ) : ?>
                                <span class="psfid-pill psfid-pill-success"><?php esc_html_e( 'Plugin detected', 'pure-smart-form-id' ); ?></span>
                            <?php else : ?>
                                <span class="psfid-pill psfid-pill-muted"><?php esc_html_e( 'Plugin not active', 'pure-smart-form-id' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="psfid-section">
            <div class="psfid-section-header">
                <h2><?php esc_html_e( 'CRM connection', 'pure-smart-form-id' ); ?></h2>
                <p><?php esc_html_e( 'Optional. Sync captured tokens and contact data to your CRM.', 'pure-smart-form-id' ); ?></p>
            </div>
            <div class="psfid-section-body">
                <table class="form-table">
                    <tr>
                        <th><label><?php esc_html_e( 'Provider', 'pure-smart-form-id' ); ?></label></th>
                        <td>
                            <select name="crm_provider" id="psfid-crm-provider">
                                <option value="none"           <?php selected( $s['crm_provider'] ?? 'none', 'none' ); ?>><?php esc_html_e( 'No connection', 'pure-smart-form-id' ); ?></option>
                                <option value="fluentcrm"      <?php selected( $s['crm_provider'] ?? 'none', 'fluentcrm' ); ?>>FluentCRM <?php echo function_exists( 'FluentCrmApi' ) ? '' : '(' . esc_html__( 'not active', 'pure-smart-form-id' ) . ')'; ?></option>
                                <option value="activecampaign" <?php selected( $s['crm_provider'] ?? 'none', 'activecampaign' ); ?>>ActiveCampaign</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <div id="psfid-ac-settings" style="<?php echo ($s['crm_provider'] ?? 'none') === 'activecampaign' ? '' : 'display:none'; ?>">
                    <h3 style="margin-top:24px">ActiveCampaign</h3>
                    <table class="form-table">
                        <tr>
                            <th><label><?php esc_html_e( 'API URL', 'pure-smart-form-id' ); ?></label></th>
                            <td>
                                <input type="url" name="ac_api_url" id="psfid-ac-url" value="<?php echo esc_attr( $s['ac_api_url'] ?? '' ); ?>" class="regular-text" placeholder="https://yourname.api-us1.com">
                                <p class="description"><?php esc_html_e( 'Found in ActiveCampaign under Settings &rarr; Developer.', 'pure-smart-form-id' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e( 'API Key', 'pure-smart-form-id' ); ?></label></th>
                            <td>
                                <input type="password" name="ac_api_key" id="psfid-ac-key" value="<?php echo esc_attr( $s['ac_api_key'] ?? '' ); ?>" class="regular-text" autocomplete="new-password">
                                <button type="button" class="button" id="psfid-ac-test"><?php esc_html_e( 'Test connection', 'pure-smart-form-id' ); ?></button>
                                <span id="psfid-ac-test-result"></span>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e( 'List ID', 'pure-smart-form-id' ); ?></label></th>
                            <td>
                                <input type="number" name="ac_list_id" value="<?php echo (int) ( $s['ac_list_id'] ?? 0 ); ?>" min="0">
                                <p class="description"><?php esc_html_e( 'Optional. Contacts will be added to this list. 0 = do not add to a list.', 'pure-smart-form-id' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php esc_html_e( 'Custom field ID for token', 'pure-smart-form-id' ); ?></label></th>
                            <td>
                                <input type="number" name="ac_field_id" value="<?php echo (int) ( $s['ac_field_id'] ?? 0 ); ?>" min="0">
                                <p class="description"><?php esc_html_e( 'Optional. Create a custom field in ActiveCampaign (e.g. "PSFID Token") and enter its ID here.', 'pure-smart-form-id' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if ( function_exists( 'FluentCrmApi' ) ) : ?>
                    <div id="psfid-fcrm-info" style="<?php echo ($s['crm_provider'] ?? 'none') === 'fluentcrm' ? '' : 'display:none'; ?>">
                        <h3 style="margin-top:24px">FluentCRM</h3>
                        <table class="form-table">
                            <tr>
                                <th><label><?php esc_html_e( 'Token custom field slug', 'pure-smart-form-id' ); ?></label></th>
                                <td>
                                    <input type="text" name="fc_token_field_slug" value="<?php echo esc_attr( $s['fc_token_field_slug'] ?? 'psfid_token' ); ?>" class="regular-text" placeholder="psfid_token">
                                    <p class="description">
                                        <?php esc_html_e( 'The FluentCRM custom field where the PSFID token is written on each sync. Default: psfid_token.', 'pure-smart-form-id' ); ?>
                                        <br>
                                        <strong><?php esc_html_e( 'Warning:', 'pure-smart-form-id' ); ?></strong>
                                        <?php esc_html_e( 'do not point this at a field you already use for URL recognition (e.g. random_userid), or PSFID will overwrite those existing values.', 'pure-smart-form-id' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <div class="psfid-callout psfid-callout-success" style="margin-top:8px">
                            <p>
                                <?php
                                printf(
                                    /* translators: %s: custom field slug */
                                    wp_kses_post( __( 'On save, PSFID tries to create the custom field %s automatically. If your FluentCRM version does not allow this, create a Text custom field with that exact slug manually under FluentCRM -> Settings -> Custom Fields.', 'pure-smart-form-id' ) ),
                                    '<code>' . esc_html( $s['fc_token_field_slug'] ?? 'psfid_token' ) . '</code>'
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="psfid-submit-row">
            <?php submit_button( __( 'Save integrations', 'pure-smart-form-id' ), 'primary', 'submit', false ); ?>
        </div>
    </form>
</div>
