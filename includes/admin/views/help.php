<?php
defined( 'ABSPATH' ) || exit;
$s     = (array) get_option( 'psfid_settings', [] );
$param = $s['url_param'] ?? 'id';
$field = $s['token_field_name'] ?? 'form_token';
?>
<div class="wrap psfid-wrap">
    <h1><?php esc_html_e( 'Help &amp; Quick start', 'pure-smart-form-id' ); ?></h1>

    <div class="psfid-help-layout">
    <div class="psfid-help-main">

    <div class="psfid-section" id="psfid-help-intro">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'What does this plugin do?', 'pure-smart-form-id' ); ?></h2>
        </div>
        <div class="psfid-section-body">
            <p style="margin-top:0"><?php esc_html_e( 'This plugin gives every visitor a unique short code (token), so you can remember form data without putting personal information in URLs. Avoids GDPR issues, ad-blocker problems, and keeps cross-form data consistent.', 'pure-smart-form-id' ); ?></p>
        </div>
    </div>

    <div class="psfid-section" id="psfid-help-step1">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'Step 1 - Configure the plugin', 'pure-smart-form-id' ); ?></h2>
        </div>
        <div class="psfid-section-body">
            <ol style="margin-top:0">
                <li>
                    <?php
                    printf(
                        /* translators: %s: link to integrations page */
                        wp_kses_post( __( 'Go to %s and enable your form plugin(s).', 'pure-smart-form-id' ) ),
                        '<a href="' . esc_url( admin_url( 'admin.php?page=psfid-integrations' ) ) . '">' . esc_html__( 'Integrations', 'pure-smart-form-id' ) . '</a>'
                    );
                    ?>
                </li>
                <li><?php esc_html_e( 'Optional: choose a CRM (FluentCRM or ActiveCampaign).', 'pure-smart-form-id' ); ?></li>
                <li>
                    <?php
                    printf(
                        /* translators: %s: link to settings page */
                        wp_kses_post( __( 'Check the %s and adjust retention periods to your liking.', 'pure-smart-form-id' ) ),
                        '<a href="' . esc_url( admin_url( 'admin.php?page=psfid-settings' ) ) . '">' . esc_html__( 'Settings', 'pure-smart-form-id' ) . '</a>'
                    );
                    ?>
                </li>
            </ol>
        </div>
    </div>

    <div class="psfid-section" id="psfid-help-step2">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'Step 2 - Pass the token to your CRM', 'pure-smart-form-id' ); ?></h2>
        </div>
        <div class="psfid-section-body">
            <p style="margin-top:0">
                <?php
                printf(
                    /* translators: %s: hidden field name */
                    wp_kses_post( __( 'The plugin automatically adds a hidden field %s to every form. In your form mapping (Fluent Forms / Gravity Forms), connect this field to a custom field in your CRM.', 'pure-smart-form-id' ) ),
                    '<code>' . esc_html( $field ) . '</code>'
                );
                ?>
            </p>

            <h3><?php esc_html_e( 'With FluentCRM', 'pure-smart-form-id' ); ?></h3>
            <p>
                <?php
                printf(
                    /* translators: 1: custom field slug, 2: merge tag */
                    wp_kses_post( __( 'The custom field %1$s is created automatically. In FluentCRM emails you can use the token as %2$s.', 'pure-smart-form-id' ) ),
                    '<code>psfid_token</code>',
                    '<code>{{contact.custom.psfid_token}}</code>'
                );
                ?>
            </p>

            <h3><?php esc_html_e( 'With ActiveCampaign', 'pure-smart-form-id' ); ?></h3>
            <p style="margin-bottom:0"><?php esc_html_e( 'Create a custom field (e.g. name: PSFID Token, type: Text). Enter that field\'s ID on the Integrations page.', 'pure-smart-form-id' ); ?></p>
        </div>
    </div>

    <div class="psfid-section" id="psfid-help-step3">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'Step 3 - Email with the token in the link', 'pure-smart-form-id' ); ?></h2>
        </div>
        <div class="psfid-section-body">
            <p style="margin-top:0"><?php esc_html_e( 'From your CRM, send follow-up emails with links like:', 'pure-smart-form-id' ); ?></p>
            <pre><code>https://yoursite.com/step2/?<?php echo esc_html( $param ); ?>={{contact.custom.psfid_token}}</code></pre>
            <p style="margin-bottom:0"><?php esc_html_e( 'When the customer clicks the link, the next form is automatically pre-filled with the known data.', 'pure-smart-form-id' ); ?></p>
        </div>
    </div>

    <div class="psfid-section" id="psfid-help-step4">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'Step 4 - Display data on pages', 'pure-smart-form-id' ); ?></h2>
            <p><?php esc_html_e( 'Use these shortcodes:', 'pure-smart-form-id' ); ?></p>
        </div>
        <div class="psfid-section-body">
            <table class="widefat">
                <thead>
                    <tr>
                        <th style="width:40%"><?php esc_html_e( 'Shortcode', 'pure-smart-form-id' ); ?></th>
                        <th><?php esc_html_e( 'What it does', 'pure-smart-form-id' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[psfid key="first_name"]</code></td>
                        <td><?php esc_html_e( 'Shows the first name if known, nothing otherwise.', 'pure-smart-form-id' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[psfid key="first_name" fallback="dear customer"]</code></td>
                        <td><?php esc_html_e( 'Shows first name, or "dear customer" if unknown.', 'pure-smart-form-id' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[psfid key="email" wrap="strong"]</code></td>
                        <td><?php esc_html_e( 'Wraps in a different HTML element (default: span).', 'pure-smart-form-id' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[psfid_token]</code></td>
                        <td><?php esc_html_e( 'Just the token string itself.', 'pure-smart-form-id' ); ?></td>
                    </tr>
                    <tr>
                        <td><code>[psfid_if key="first_name"]Hi {first_name}![/psfid_if]</code></td>
                        <td><?php esc_html_e( 'Renders only if the first name is known. Curly braces are replaced with the value.', 'pure-smart-form-id' ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="psfid-section" id="psfid-help-fields">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'How to set up your form fields', 'pure-smart-form-id' ); ?></h2>
        </div>
        <div class="psfid-section-body">
            <div class="psfid-callout psfid-callout-success" style="margin-top:0">
                <p><strong><?php esc_html_e( 'Recommended: always use the dedicated field type.', 'pure-smart-form-id' ); ?></strong> <?php esc_html_e( 'It works automatically in any language, validates input, and never breaks if you rename a label.', 'pure-smart-form-id' ); ?></p>
            </div>

            <h3><?php esc_html_e( 'Recommended approach: use the right field type', 'pure-smart-form-id' ); ?></h3>
            <p><?php esc_html_e( 'In Gravity Forms and Fluent Forms, use the dedicated field types instead of generic "Single Line Text". This is the only setup we actively support and recommend:', 'pure-smart-form-id' ); ?></p>
            <ul>
                <li><strong><?php esc_html_e( 'Email field', 'pure-smart-form-id' ); ?></strong> &rarr; <?php esc_html_e( 'recognized as', 'pure-smart-form-id' ); ?> <code>email</code></li>
                <li><strong><?php esc_html_e( 'Phone field', 'pure-smart-form-id' ); ?></strong> &rarr; <code>phone</code></li>
                <li><strong><?php esc_html_e( 'Name field', 'pure-smart-form-id' ); ?></strong> &rarr; <code>first_name</code> + <code>last_name</code> (sub-fields)</li>
                <li><strong><?php esc_html_e( 'Address field', 'pure-smart-form-id' ); ?></strong> &rarr; <code>address_line_1</code>, <code>postal_code</code>, <code>city</code>, <code>country</code></li>
            </ul>
            <p><?php esc_html_e( 'These types are language-independent: it does not matter what label you give them. A Phone field labelled "Wat is jouw mobiele nummer?" is automatically recognized as phone.', 'pure-smart-form-id' ); ?></p>

            <h3><?php esc_html_e( 'Alternative: set the Parameter Name', 'pure-smart-form-id' ); ?></h3>
            <p><?php esc_html_e( 'Only use this if you cannot replace an existing Single Line Text field, or if you need multiple phone/email fields in one form (e.g. work phone vs personal phone) that should map to different keys.', 'pure-smart-form-id' ); ?></p>
            <p><strong><?php esc_html_e( 'Gravity Forms:', 'pure-smart-form-id' ); ?></strong> <?php esc_html_e( 'Edit the field &rarr; Advanced tab &rarr; tick "Allow field to be populated dynamically" &rarr; enter the standard key as Parameter Name (e.g. phone, email, first_name, company).', 'pure-smart-form-id' ); ?></p>
            <p><strong><?php esc_html_e( 'Fluent Forms:', 'pure-smart-form-id' ); ?></strong> <?php esc_html_e( 'Edit the field &rarr; Element Settings &rarr; set "Name Attribute" to the standard key (e.g. phone, email, first_name, company).', 'pure-smart-form-id' ); ?></p>

            <div class="psfid-callout psfid-callout-warning">
                <p><strong><?php esc_html_e( 'Last resort: label fallback', 'pure-smart-form-id' ); ?></strong></p>
                <p><?php esc_html_e( 'As a safety net, the plugin tries to recognize labels containing words like "telefoon", "mobiel", "phone", "email", "bedrijf", "company". This is fragile: it breaks the moment you rename a label or use a synonym. Use it only when you have absolutely no control over the form (e.g. a third-party form you cannot modify). Not recommended for production.', 'pure-smart-form-id' ); ?></p>
                <p><?php esc_html_e( 'If none of the three options apply, the field is stored under its raw label. Cross-form pre-fill still works for identical labels, but the email/phone match-fallback and CRM mapping will not detect it.', 'pure-smart-form-id' ); ?></p>
            </div>

            <h3><?php esc_html_e( 'Standard keys overview', 'pure-smart-form-id' ); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th style="width:30%"><?php esc_html_e( 'Standard key', 'pure-smart-form-id' ); ?></th>
                        <th><?php esc_html_e( 'For', 'pure-smart-form-id' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>first_name</code></td><td><?php esc_html_e( 'First name', 'pure-smart-form-id' ); ?></td></tr>
                    <tr><td><code>last_name</code></td><td><?php esc_html_e( 'Last name', 'pure-smart-form-id' ); ?></td></tr>
                    <tr><td><code>email</code></td><td><?php esc_html_e( 'Email address', 'pure-smart-form-id' ); ?></td></tr>
                    <tr><td><code>phone</code></td><td><?php esc_html_e( 'Phone / mobile number', 'pure-smart-form-id' ); ?></td></tr>
                    <tr><td><code>company</code></td><td><?php esc_html_e( 'Company name', 'pure-smart-form-id' ); ?></td></tr>
                    <tr><td><code>address_line_1</code></td><td><?php esc_html_e( 'Street address', 'pure-smart-form-id' ); ?></td></tr>
                    <tr><td><code>postal_code</code></td><td><?php esc_html_e( 'Postal code', 'pure-smart-form-id' ); ?></td></tr>
                    <tr><td><code>city</code></td><td><?php esc_html_e( 'City', 'pure-smart-form-id' ); ?></td></tr>
                    <tr><td><code>country</code></td><td><?php esc_html_e( 'Country', 'pure-smart-form-id' ); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="psfid-section" id="psfid-help-cookies">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'Cookies and recognition', 'pure-smart-form-id' ); ?></h2>
            <p><?php esc_html_e( 'The plugin recognizes a visitor in this order:', 'pure-smart-form-id' ); ?></p>
        </div>
        <div class="psfid-section-body">
            <?php
            $auto_link_actief = ! isset( $s['auto_link_wp_user'] ) ? true : (bool) $s['auto_link_wp_user'];
            ?>
            <ol style="margin-top:0">
                <li>
                    <?php
                    printf(
                        /* translators: %s: URL parameter format */
                        wp_kses_post( __( 'URL parameter %s', 'pure-smart-form-id' ) ),
                        '<code>?' . esc_html( $param ) . '=token</code>'
                    );
                    ?>
                </li>
                <li><?php esc_html_e( 'Cookie on the visitor\'s device', 'pure-smart-form-id' ); ?></li>
                <?php if ( $auto_link_actief ) : ?>
                    <li><?php esc_html_e( 'Logged-in WordPress user: matched on account email. Existing token reused, otherwise a new one is created (source: wp_user_auto).', 'pure-smart-form-id' ); ?></li>
                <?php endif; ?>
                <?php if ( ! empty( $s['email_lookup_enabled'] ) ) : ?>
                    <li><?php esc_html_e( 'Email match: when the visitor enters the same email again, the existing token is reused.', 'pure-smart-form-id' ); ?></li>
                <?php endif; ?>
                <?php if ( ! empty( $s['phone_lookup_enabled'] ) ) : ?>
                    <li><?php esc_html_e( 'Phone match: when the visitor enters the same phone number again.', 'pure-smart-form-id' ); ?></li>
                <?php endif; ?>
                <li><?php esc_html_e( 'New token if none of the above match.', 'pure-smart-form-id' ); ?></li>
            </ol>
        </div>
    </div>

    <div class="psfid-section" id="psfid-help-wp-user">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'Automatic WordPress user recognition', 'pure-smart-form-id' ); ?></h2>
            <p><?php esc_html_e( 'New in 1.3.0', 'pure-smart-form-id' ); ?></p>
        </div>
        <div class="psfid-section-body">
            <p style="margin-top:0"><?php esc_html_e( 'Logged-in WordPress users are automatically linked to a Pure Smart Form ID token via their account email address. This means forms pre-fill with the user\'s known data without them needing to click a link with a token.', 'pure-smart-form-id' ); ?></p>

            <h3><?php esc_html_e( 'When to use it', 'pure-smart-form-id' ); ?></h3>
            <ul>
                <li><?php esc_html_e( 'Member portals where users fill in forms regularly (coaching, training, support)', 'pure-smart-form-id' ); ?></li>
                <li><?php esc_html_e( 'Customer accounts in WooCommerce where checkout-related forms should pre-fill', 'pure-smart-form-id' ); ?></li>
                <li><?php esc_html_e( 'Internal staff forms where the WordPress login is enough identification', 'pure-smart-form-id' ); ?></li>
            </ul>

            <h3><?php esc_html_e( 'When to turn it off', 'pure-smart-form-id' ); ?></h3>
            <p>
                <?php
                printf(
                    /* translators: %s: link to settings page */
                    wp_kses_post( __( 'On sites with many logged-in non-form visitors (forum members, free-trial accounts, large customer base) you may not want a token generated for every visitor who never fills in a form. Turn the setting off in %s.', 'pure-smart-form-id' ) ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=psfid-settings' ) ) . '">' . esc_html__( 'Settings -> Identification fallbacks', 'pure-smart-form-id' ) . '</a>'
                );
                ?>
            </p>

            <p style="margin-bottom:0"><?php esc_html_e( 'Auto-generated tokens have source "wp_user_auto" in the Tokens overview, so you can identify and clean them up separately if needed.', 'pure-smart-form-id' ); ?></p>
        </div>
    </div>

    <?php if ( function_exists( 'FluentCrmApi' ) ) : ?>
    <div class="psfid-section" id="psfid-help-fc-source">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'FluentCRM as data source', 'pure-smart-form-id' ); ?></h2>
            <p><?php esc_html_e( 'New in 1.3.0', 'pure-smart-form-id' ); ?></p>
        </div>
        <div class="psfid-section-body">
            <p style="margin-top:0"><?php esc_html_e( 'When FluentCRM is active, Pure Smart Form ID can read contact data directly from FluentCRM and use it for form pre-fill and shortcodes. This makes FluentCRM the master record for contact information; PSFID tokens stay leading for identification.', 'pure-smart-form-id' ); ?></p>

            <h3><?php esc_html_e( 'What gets merged', 'pure-smart-form-id' ); ?></h3>
            <ul>
                <li><?php esc_html_e( 'Standard fields: first_name, last_name, email, phone, company, address_line_1, address_line_2, postal_code, city, state, country', 'pure-smart-form-id' ); ?></li>
                <li><?php esc_html_e( 'All custom_values stored on the FluentCRM contact', 'pure-smart-form-id' ); ?></li>
            </ul>

            <h3><?php esc_html_e( 'Conflict rule', 'pure-smart-form-id' ); ?></h3>
            <p><?php esc_html_e( 'FluentCRM wins when both PSFID and FluentCRM have a value for the same key. Empty FluentCRM values never overwrite PSFID data, so a blank custom field will not erase a value the visitor previously entered through a form.', 'pure-smart-form-id' ); ?></p>

            <h3><?php esc_html_e( 'Restricting which fields are merged', 'pure-smart-form-id' ); ?></h3>
            <p><?php esc_html_e( 'By default, all available fields are merged. To limit this, add a filter in your theme or a custom plugin:', 'pure-smart-form-id' ); ?></p>
            <pre><code>add_filter( 'psfid_fc_source_fields', function ( $velden, $contact, $email ) {
    // Whitelist: only first_name, last_name and phone
    return array_intersect_key( $velden, array_flip( [
        'first_name', 'last_name', 'phone'
    ] ) );
}, 10, 3 );</code></pre>
        </div>
    </div>
    <?php endif; ?>

    <div class="psfid-section" id="psfid-help-faq">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'Frequently asked questions', 'pure-smart-form-id' ); ?></h2>
        </div>
        <div class="psfid-section-body">
            <h3 style="margin-top:0"><?php esc_html_e( 'Does this work across multiple domains?', 'pure-smart-form-id' ); ?></h3>
            <p>
                <?php
                printf(
                    /* translators: %s: URL parameter format */
                    wp_kses_post( __( 'Cookies work per domain. Between domains, you pass the token via the URL (%s). You do that via your CRM email.', 'pure-smart-form-id' ) ),
                    '<code>?' . esc_html( $param ) . '=token</code>'
                );
                ?>
            </p>

            <h3><?php esc_html_e( 'What if a customer clears their cookies?', 'pure-smart-form-id' ); ?></h3>
            <p><?php esc_html_e( 'With the email match fallback enabled, the customer is recognized as soon as they enter their email again. No duplicate records.', 'pure-smart-form-id' ); ?></p>

            <h3><?php esc_html_e( 'How long is data retained?', 'pure-smart-form-id' ); ?></h3>
            <p><?php esc_html_e( 'By default 90 days after last activity (configurable). After that, the record is deleted automatically. Manual cleanup can be triggered from the Dashboard.', 'pure-smart-form-id' ); ?></p>

            <h3><?php esc_html_e( 'Can I export?', 'pure-smart-form-id' ); ?></h3>
            <p style="margin-bottom:0">
                <?php
                printf(
                    /* translators: %s: link to tokens page */
                    wp_kses_post( __( 'Yes. On the %s page you can download per token or everything in one CSV. Useful as backup or when your CRM is temporarily offline.', 'pure-smart-form-id' ) ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=psfid-tokens' ) ) . '">' . esc_html__( 'Tokens', 'pure-smart-form-id' ) . '</a>'
                );
                ?>
            </p>
        </div>
    </div>

    <div class="psfid-section" id="psfid-help-pro">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'Pro features', 'pure-smart-form-id' ); ?></h2>
        </div>
        <div class="psfid-section-body">
            <?php $pro_actief = defined( 'PSFID_PRO_VERSION' ); ?>

            <p style="margin-top:0">
                <?php esc_html_e( 'Pure Smart Form ID Pro adds the following features on top of the free plugin:', 'pure-smart-form-id' ); ?>
            </p>
            <ul>
                <li><strong><?php esc_html_e( 'Delayed CRM sync', 'pure-smart-form-id' ); ?></strong> &mdash; <?php esc_html_e( 'wait configurable minutes/hours/days before pushing a contact to ActiveCampaign. Lets visitors finish multi-step journeys without ending up in your CRM after the first form.', 'pure-smart-form-id' ); ?></li>
                <li><strong><?php esc_html_e( 'Per-form list mapping', 'pure-smart-form-id' ); ?></strong> &mdash; <?php esc_html_e( 'route submissions from different forms to different AC lists.', 'pure-smart-form-id' ); ?></li>
                <li><strong><?php esc_html_e( 'Per-form tags', 'pure-smart-form-id' ); ?></strong> &mdash; <?php esc_html_e( 'tag contacts based on which form they filled in, with {field} placeholders for dynamic values.', 'pure-smart-form-id' ); ?></li>
                <li><strong><?php esc_html_e( 'Bug report page', 'pure-smart-form-id' ); ?></strong> &mdash; <?php esc_html_e( 'one-click diagnostics page (versions, scheduled crons, recent log entries) to share with support when something goes wrong.', 'pure-smart-form-id' ); ?></li>
            </ul>

            <?php if ( $pro_actief ) : ?>
                <div class="psfid-callout psfid-callout-success">
                    <p style="margin:0">
                        <strong><?php esc_html_e( 'Pro is active', 'pure-smart-form-id' ); ?> (v<?php echo esc_html( PSFID_PRO_VERSION ); ?>)</strong>
                        &mdash;
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=psfid-pro-license' ) ); ?>"><?php esc_html_e( 'Pro Instellingen', 'pure-smart-form-id' ); ?></a>
                        ·
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=psfid-pro-settings' ) ); ?>"><?php esc_html_e( 'Vertraging', 'pure-smart-form-id' ); ?></a>
                        <?php
                        $bug_aan = class_exists( 'PSFID_Pro_Bug_Report' ) && PSFID_Pro_Bug_Report::is_enabled();
                        if ( $bug_aan ) :
                            ?>
                            ·
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=psfid-pro-bug-report' ) ); ?>"><?php esc_html_e( 'Bug Report', 'pure-smart-form-id' ); ?></a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php else : ?>
                <p style="margin-bottom:0">
                    <a href="https://pureplugins.eu/pure-smart-form-id-pro" target="_blank" rel="noopener" class="button button-primary">
                        <?php esc_html_e( 'Get Pure Smart Form ID Pro', 'pure-smart-form-id' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="psfid-section" id="psfid-help-dev">
        <div class="psfid-section-header">
            <h2><?php esc_html_e( 'For developers', 'pure-smart-form-id' ); ?></h2>
        </div>
        <div class="psfid-section-body">
            <p style="margin-top:0"><?php esc_html_e( 'Available hooks:', 'pure-smart-form-id' ); ?></p>
            <ul>
                <li><code>do_action('psfid/token_captured', $token_id, $token_string, $form_data, $source, $form_id)</code> - <?php esc_html_e( 'fired after every successful capture', 'pure-smart-form-id' ); ?></li>
                <li><code>apply_filters('psfid_has_consent', false)</code> - <?php esc_html_e( 'cookie consent integration', 'pure-smart-form-id' ); ?></li>
            </ul>

            <p><?php esc_html_e( 'REST endpoint:', 'pure-smart-form-id' ); ?></p>
            <pre style="margin-bottom:0"><code>GET <?php echo esc_html( rest_url( 'psfid/v1/data/{token}' ) ); ?></code></pre>
        </div>
    </div>

    </div><!-- .psfid-help-main -->

    <aside class="psfid-help-toc">
        <div class="psfid-help-toc-title"><?php esc_html_e( 'Jump to', 'pure-smart-form-id' ); ?></div>
        <ul>
            <li><a href="#psfid-help-intro"><?php esc_html_e( 'What does this plugin do?', 'pure-smart-form-id' ); ?></a></li>
            <li><a href="#psfid-help-step1"><?php esc_html_e( 'Step 1 - Configure', 'pure-smart-form-id' ); ?></a></li>
            <li><a href="#psfid-help-step2"><?php esc_html_e( 'Step 2 - CRM', 'pure-smart-form-id' ); ?></a></li>
            <li><a href="#psfid-help-step3"><?php esc_html_e( 'Step 3 - Email link', 'pure-smart-form-id' ); ?></a></li>
            <li><a href="#psfid-help-step4"><?php esc_html_e( 'Step 4 - Display data', 'pure-smart-form-id' ); ?></a></li>
            <li><a href="#psfid-help-fields"><?php esc_html_e( 'Form field setup', 'pure-smart-form-id' ); ?></a></li>
            <li><a href="#psfid-help-cookies"><?php esc_html_e( 'Cookies', 'pure-smart-form-id' ); ?></a></li>
            <li><a href="#psfid-help-wp-user"><?php esc_html_e( 'WordPress users', 'pure-smart-form-id' ); ?></a></li>
            <?php if ( function_exists( 'FluentCrmApi' ) ) : ?>
                <li><a href="#psfid-help-fc-source"><?php esc_html_e( 'FluentCRM data source', 'pure-smart-form-id' ); ?></a></li>
            <?php endif; ?>
            <li><a href="#psfid-help-faq"><?php esc_html_e( 'FAQ', 'pure-smart-form-id' ); ?></a></li>
            <li><a href="#psfid-help-pro"><?php esc_html_e( 'Pro features', 'pure-smart-form-id' ); ?></a></li>
            <li><a href="#psfid-help-dev"><?php esc_html_e( 'Developers', 'pure-smart-form-id' ); ?></a></li>
        </ul>
    </aside>

    </div><!-- .psfid-help-layout -->
</div>
