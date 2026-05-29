=== Pure Smart Form ID ===
Contributors: nomaxx
Author: Marco van Ham
Author URI: https://www.marcovanham.nl
Tags: forms, tokens, gdpr, prefill, crm
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.5.0
License: GPL-2.0-or-later

Smart form tokens for cross-form pre-fill, multi-step funnels and CRM integration. GDPR-friendly: no personal data in URLs.

== Description ==

Pure Smart Form ID replaces readable URL parameters with a short anonymous token, so personal data never ends up in your URLs. The real data stays safely in your own database. Works with Fluent Forms and Gravity Forms.

= Why use this? =

The naive way to carry data between pages is to put it in the URL: `?email=visitor@example.com&name=John`. That data then shows up in three places you do not control:

* Server logs and analytics keep a copy of every URL, personal data included.
* Ad networks (Google Ads, Meta) receive the URL as a referrer. Sending them personal data breaks their policies and can get your landing pages disapproved or, with repeated violations, your ad account suspended.
* The visitor's browser history and any shared link expose the data to anyone who sees it.

This is not only about multi-step forms. A "Thank you, John" confirmation page that carries the name in the URL has the exact same problem, and that page is usually where your conversion pixel fires, sending the personal data straight to the ad network.

Pure Smart Form ID replaces all of that with a short anonymous token like `?id=k7Q3xR9pM2vN4tL8`. The real data stays in your own database. The visitor is recognized by the token, a functional first-party cookie that stores only the anonymous token (never personal data), or as a fallback their email or phone on the next submission. No personal data in URLs, no leaks to logs or ad networks, no duplicate records, and it keeps working across devices.

Turn it around and it becomes a marketing tool: pass the token in your CRM email links (`?id=token`) and the landing page can greet returning visitors and pre-fill their details. More personal, higher-converting, and still no personal data in the URL.

**Features:**

* Short unique tokens instead of readable data
* Works with Fluent Forms and Gravity Forms
* Automatic pre-fill of follow-up forms
* Functional first-party cookie (anonymous token only) for returning visitors
* Email match fallback for cookie-less identification
* Multi-step funnel support (no duplicate records)
* Shortcodes with fallback for displaying data
* Optional FluentCRM or ActiveCampaign integration
* CSV export for backup
* Configurable retention periods (cookie, data, log)
* Whitelabel: customizable name, menu, links
* Audit log for verification
* Multilingual: English, Dutch, French, German and Spanish (extra languages easy to add)

**For who:**

* Multi-step forms (intake, quote, lead funnel)
* GDPR-conscious websites
* Sites that want to avoid asking the same data twice
* Agencies offering it under their own brand

= About the author =

Pure Smart Form ID is built by Marco van Ham, founder of NOMAXX in Rotterdam. With 25+ years of web development experience and 18 years in WordPress (300+ projects), Marco focuses on plugins that solve real-world problems for European businesses: GDPR-first by design, properly documented in English and Dutch. Pure Smart Form ID is the first plugin in the Pure Plugins line. Learn more at pureplugins.eu.

== Installation ==

1. Upload the `pure-smart-form-id` folder to `/wp-content/plugins/`
2. Activate the plugin via 'Plugins' in WordPress
3. Go to Form ID &rarr; Integrations and enable the form plugin(s) you use
4. Optionally configure a CRM connection
5. Check the Help page for detailed examples

== Frequently Asked Questions ==

= Does it work without FluentCRM? =

Yes. The plugin has its own database. FluentCRM and ActiveCampaign are optional.

= What if a visitor clears their cookies? =

With the email match fallback enabled, the visitor is recognized when they re-enter their email. No duplicate records.

= Does it work across multiple domains? =

Cookies work per domain. Across domains, you pass the token via the URL (`?id=token`).

= Is the plugin available in my language? =

The plugin ships with English, Dutch, French, German and Spanish. Extra languages can be added by dropping a `.po`/`.mo` file in the `/languages/` folder.

== Languages ==

English, Dutch, French, German and Spanish. Easy to add others by dropping a .po file in /languages/.

== Screenshots ==

1. Dashboard with token statistics and quick actions.
2. Integrations page: enable Fluent Forms or Gravity Forms and connect a CRM.
3. Settings: retention periods, identification fallbacks, and FluentCRM data source.
4. Tokens overview with search, source, and CRM sync status.
5. Help page with step-by-step setup and shortcode reference.

== External services ==

This plugin does not connect to any external service by default. Out of the box, all data stays in your own WordPress database.

= ActiveCampaign (optional, opt-in only) =

If, and only if, you actively choose ActiveCampaign as your CRM provider and enter your own ActiveCampaign API URL and API key under Form ID -> Integrations, the plugin will send contact data to your own ActiveCampaign account when a form is submitted.

* What is sent: the contact email address, first name, last name, phone number, and the PSFID token (only the fields that are present).
* When it is sent: only after you configure ActiveCampaign, and only on form submission or token capture.
* Where it is sent: to the ActiveCampaign account you connect (the API URL you provide), via the official ActiveCampaign REST API (/api/3/contact/sync, /api/3/contactLists).
* Why: to create or update the contact in your own CRM so you can follow up.

No data is sent to ActiveCampaign until you explicitly enter your own credentials. If you leave the CRM provider on "No connection" (the default), nothing leaves your site.

ActiveCampaign is a third-party service. Their terms and privacy policy apply:

* Terms: https://www.activecampaign.com/legal/terms
* Privacy: https://www.activecampaign.com/legal/privacy-policy

= FluentCRM (optional, local only) =

If FluentCRM is installed, the plugin can read from and write to it. FluentCRM runs inside your own WordPress installation, so this is not an external service and no data leaves your server.

== Changelog ==

= 1.5.0 =
* WordPress.org release. No functional changes versus 1.4.0.
* Hardening: all table-existence checks now use prepared statements (passes Plugin Check).
* readme: trimmed tags, added French to the bundled languages, added screenshots section, documented the optional ActiveCampaign external service.

= 1.4.0 =
* New: FluentCRM contact recognition via URL. A URL parameter (e.g. ?rid=VALUE) is matched live against a FluentCRM custom field (e.g. random_userid). The visitor is recognized and the form pre-fills, without changing anything in FluentCRM. Existing email links keep working. Configure under Settings -> FluentCRM contact recognition via URL.
* New: Configurable FluentCRM token custom field slug (default psfid_token) under Integrations. PSFID writes the token to this field on sync.
* Improved: custom field auto-creation is now defensive across FluentCRM versions, with an honest fallback message if it cannot be created automatically.
* Auto-detect between fc_subscriber_meta and fc_contact_meta storage, so the URL recognition works across FluentCRM versions.

= 1.3.0 =
* New: Auto-detect logged-in WordPress users. Existing tokens are matched by account email; new tokens are created automatically (source: wp_user_auto). Settable on or off via Settings -> Identification fallbacks.
* New: FluentCRM as data source. When FluentCRM is active, contact fields and custom_values are merged into PSFID resolver fields. Forms pre-fill with the latest CRM data automatically. FluentCRM wins on conflicts. Toggle via Settings -> FluentCRM data source.
* New: Filter `psfid_fc_source_fields` to whitelist which FluentCRM fields are merged.
* New: Helper `PSFID_Token::zoek_op_id()` for direct lookups by internal ID.
* Migration helper: companion plugin "Pure Smart Form ID Legacy Bridge" added (separate install) for sites coming from the older NOMAXX FluentCRM Data Connector. Handles `[fc_data]` shortcode alias, `?rid=` URL parameter, and `nomaxx_crm_data` cookie migration. Activate temporarily during the switch, then deactivate.

= 1.2.0 =
* Internal release: hardening + Pro plugin compatibility.

= 1.1.9 =
* Critical fix: Multi-page resume now uses the correct GF URL parameter (gf_token in modern GF 2.5+) instead of the legacy gform_resume_token only. Both parameters are now passed in the redirect URL for compatibility with older GF versions.
* Without this fix, GF treated the redirect as a regular form load, creating a NEW entry instead of resuming the existing draft.

= 1.1.8 =
* Fix: Multi-page resume now triggers via gform_pre_render hook so it works regardless of how the form is embedded (shortcode, Gutenberg block, Elementor widget, Divi module, custom theme template)
* Fix: Email match for GF drafts is now case-insensitive (Marco@example.com matches marco@example.com)
* New: Audit log entry for every draft check, visible in token detail view
* New: Tokens detail view shows all GF Save & Continue drafts for the email of this token (debug helper)
* Console message logged when auto-resume triggers, useful for verification in DevTools

= 1.1.7 =
* Fix: Help page card styling and sticky TOC sidebar now render correctly (CSS specificity raised so WP core admin styling cannot override)
* Fix: All config pages (Settings, Integrations, Fields, Tokens, Branding, Help) consistently use card layout with grey header strip and clean body
* Subtle box-shadow added to all section cards for better visual hierarchy
* TOC sidebar now scrollable when very long, max-height limited to viewport
* CSS bumped to v1.1.7 for automatic cache busting

= 1.1.6 =
* New: Gravity Forms multi-page auto-resume - when a visitor returns via a PSFID link, the plugin redirects with gform_resume_token so GF jumps directly to the page where the visitor stopped (instead of starting at page 1)
* Server-side detection of GF shortcodes in post content for clean redirect
* JS fallback for Gutenberg blocks and dynamically embedded forms via REST endpoint
* Session storage guard prevents redirect loops in SPAs

= 1.1.5 =
* New: Save & Continue support for Gravity Forms - partial form data is automatically restored when a visitor returns via a PSFID link
* New: Save & Resume support for Fluent Forms (Pro) - same flow for Fluent Forms drafts
* New: Help page now has a sticky table-of-contents sidebar for quick navigation between sections
* Fix: WP dashboard widget styling - 3 stats now correctly displayed in columns with proper spacing
* Fix: WP dashboard widget more breathing room around action buttons

= 1.1.4 =
* New: WordPress dashboard widget showing total/active/24h stats and recent activity right after login
* New: About card on the plugin Dashboard with credits and links to pureplugins.eu
* New: admin footer credit "Made by Marco van Ham · pureplugins.eu" on plugin pages
* Removed: Branding submenu (whitelabel via wp-config.php constants still works for power users)
* Email masking in dashboard widget for privacy in screenshots/screenshares (e.g. mar***@n***.nl)

= 1.1.3 =
* All admin pages now use a consistent card-based layout (matches the dashboard look)
* Settings, Integrations, Branding, Fields, Tokens detail and Help all wrapped in section cards with header + body
* Status pills (green/grey) for "Plugin detected" and "CRM synced" indicators
* CSS reset for form-table inside cards for cleaner look
* Reduced visual noise: less default WordPress spacing inside content areas

= 1.1.2 =
* Help page rewritten: dedicated field type is now the only recommended setup
* Parameter Name positioned as alternative for edge cases
* Label fallback marked as "last resort, not recommended for production"
* Visual callouts (success/warning) added on the Help page

= 1.1.1 =
* Fix: Gravity Forms composite fields (Name, Address) are now correctly pre-filled across forms
* Fix: GF capture uses field type detection (email, phone) so language-independent labels work
* Fix: Phone fields with custom labels like "What is your mobile?" are now stored under 'phone' key
* Fix: Hidden token field is updated in the entry after capture, so the PSFID Token column is filled
* New: detailed Help section explaining how to set up form fields (field type vs parameter name vs label fallback)

= 1.1.0 =
* Full internationalization (i18n) support
* Built-in translations: English, Dutch, German, Spanish
* All admin texts now translatable

= 1.0.0 =
* First release
