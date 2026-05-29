/**
 * Pure Smart Form ID - Frontend
 *
 * - Vult formulier-velden vooraf in op basis van bekende data
 * - Synchroniseert nieuwe submissions met de cookie (cross-form pre-fill)
 * - Werkt op alle inputs met name="<key>", data-name="<key>", of names[<key>]
 */
(function () {
    'use strict';

    if (typeof window.PSFID === 'undefined') {
        return;
    }

    var config = window.PSFID;
    var data = (config.fields && typeof config.fields === 'object') ? Object.assign({}, config.fields) : {};
    var aliases = (config.aliases && typeof config.aliases === 'object') ? config.aliases : {};

    /* ---------- Cookie helpers ---------- */
    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value) +
            '; expires=' + d.toUTCString() +
            '; path=/; SameSite=Lax' + secure;
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^|;)\\s*' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[2]) : '';
    }

    /* ---------- Data helpers ---------- */
    function applyAliases(d) {
        // Voeg aliases beide kanten op toe (voornaam <-> first_name)
        for (var alias in aliases) {
            var realKey = aliases[alias];
            if (d[realKey] && !d[alias]) d[alias] = d[realKey];
            if (d[alias] && !d[realKey]) d[realKey] = d[alias];
        }
        return d;
    }

    function fetchDataFromServer(token, callback) {
        if (!token) return callback({});
        fetch(config.restUrl + 'data/' + encodeURIComponent(token))
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (json) {
                if (json && json.fields) {
                    callback(json.fields);
                } else {
                    callback({});
                }
            })
            .catch(function () { callback({}); });
    }

    /* ---------- Auto-fill ---------- */
    function fillField(field, value) {
        if (!field || field.value === value) return;
        // Niet overschrijven als gebruiker al iets heeft ingetypt
        if (field.value && field.dataset.psfidFilled !== '1') return;
        field.value = value;
        field.dataset.psfidFilled = '1';
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function injectTokenField() {
        // Voeg een hidden token-field toe aan formulieren die het nog niet hebben.
        // Robuuste fallback voor wanneer server-side auto-inject niet beschikbaar is.
        if (!config.tokenField || !config.token) return;

        var forms = document.querySelectorAll('form.fluentform, form[id^="gform_"], form.frm-fluent-form');
        forms.forEach(function (form) {
            var existing = form.querySelector('[name="' + config.tokenField + '"]');
            if (existing) {
                if (!existing.value) existing.value = config.token;
                return;
            }
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = config.tokenField;
            input.value = config.token;
            input.setAttribute('data-psfid-injected', '1');
            form.appendChild(input);
        });
    }

    function autoFillForms() {
        injectTokenField();

        if (!data || Object.keys(data).length === 0) return;

        for (var key in data) {
            var val = data[key];
            if (val === null || val === undefined || val === '') continue;

            var selectors = [
                '[name="' + key + '"]',
                '[data-name="' + key + '"]',
                '[name="names[' + key + ']"]',
                '[name="custom_values[' + key + ']"]',
                '[name="address_1[' + key + ']"]'
            ];

            // Aliases voor adres-componenten in Fluent Forms
            if (key === 'first_name') selectors.push('[name="names[first_name]"]');
            if (key === 'last_name') selectors.push('[name="names[last_name]"]');
            if (key === 'voornaam') selectors.push('[name="names[first_name]"]');
            if (key === 'achternaam') selectors.push('[name="names[last_name]"]');

            selectors.forEach(function (sel) {
                document.querySelectorAll(sel).forEach(function (el) {
                    fillField(el, val);
                });
            });
        }

        // Gravity Forms composite velden: zoek inputs op label-attribute via aria-label
        // Format input names: input_<form-field-id>.<sub-id> bijv input_1.3
        // De server-side defaultValue afhandeling is primair, dit is een back-up via labels
        fillGravityCompositeFields();

        // Vul ook het verborgen token veld
        if (config.token && config.tokenField) {
            document.querySelectorAll('[name="' + config.tokenField + '"]').forEach(function (el) {
                if (!el.value) el.value = config.token;
            });
        }
    }

    function fillGravityCompositeFields() {
        // Zoek velden binnen .ginput_complex containers (Name/Address fields)
        var labelMap = {
            'voornaam': 'first_name', 'first': 'first_name', 'first name': 'first_name', 'vorname': 'first_name', 'nombre': 'first_name',
            'achternaam': 'last_name', 'last': 'last_name', 'last name': 'last_name', 'nachname': 'last_name', 'apellido': 'last_name', 'apellidos': 'last_name',
            'plaats': 'city', 'woonplaats': 'city', 'city': 'city', 'stadt': 'city', 'ciudad': 'city',
            'postcode': 'postal_code', 'zip': 'postal_code', 'postal code': 'postal_code', 'plz': 'postal_code',
            'land': 'country', 'country': 'country', 'pais': 'country',
            'adres': 'address_line_1', 'street address': 'address_line_1', 'straße': 'address_line_1'
        };

        document.querySelectorAll('.ginput_complex input, .ginput_complex select').forEach(function (input) {
            // Skip als al gevuld
            if (input.value && input.dataset.psfidFilled !== '1') return;

            // Probeer label te vinden
            var label = '';
            if (input.id) {
                var lbl = document.querySelector('label[for="' + input.id + '"]');
                if (lbl) label = lbl.textContent.trim().toLowerCase();
            }
            if (!label && input.placeholder) {
                label = input.placeholder.toLowerCase();
            }
            if (!label && input.getAttribute('aria-label')) {
                label = input.getAttribute('aria-label').toLowerCase();
            }
            if (!label) return;

            // Map naar standaard key
            var key = labelMap[label] || labelMap[label.replace(/\s+/g, '_')];
            if (!key) return;

            if (data[key]) {
                fillField(input, data[key]);
            }
        });
    }

    function fillShortcodes() {
        document.querySelectorAll('.psfid-field').forEach(function (el) {
            var key = el.getAttribute('data-psfid-key');
            var fallback = el.getAttribute('data-psfid-fallback') || '';
            if (key && data[key]) {
                el.textContent = data[key];
            } else if (fallback && !el.textContent.trim()) {
                el.textContent = fallback;
            }
        });
    }

    /* ---------- Cross-form sync via cookie ---------- */
    function syncFromCookie() {
        var cookieToken = getCookie(config.cookieName);
        if (!cookieToken) return;

        // Als cookie token gelijk is aan huidige token, niets nieuws
        if (cookieToken === config.token && Object.keys(data).length > 0) return;

        fetchDataFromServer(cookieToken, function (fields) {
            if (Object.keys(fields).length > 0) {
                Object.assign(data, fields);
                applyAliases(data);
                config.token = cookieToken;
                autoFillForms();
                fillShortcodes();
            }
        });
    }

    /* ---------- Init ---------- */
    function init() {
        applyAliases(data);

        // Als we een token hebben, cookie verversen
        if (config.token) {
            setCookie(config.cookieName, config.token, config.cookieDays);
        } else {
            // Geen server-side token: probeer uit cookie te halen
            syncFromCookie();
        }

        autoFillForms();
        fillShortcodes();

        // Nogmaals proberen na 800ms voor formulieren die later renderen
        setTimeout(function () {
            autoFillForms();
            fillShortcodes();
        }, 800);

        // Observer voor dynamisch toegevoegde formulieren
        if (typeof MutationObserver !== 'undefined') {
            var obs = new MutationObserver(function (muts) {
                for (var i = 0; i < muts.length; i++) {
                    if (muts[i].addedNodes.length > 0) {
                        autoFillForms();
                        fillShortcodes();
                        break;
                    }
                }
            });
            obs.observe(document.body, { childList: true, subtree: true });
        }
    }

    /* ---------- Form submit hooks ---------- */
    // Fluent Forms succes-event
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('fluentform_submission_success', function (e, payload) {
            try {
                var formId = payload && payload.formId;
                if (!formId) return;

                var $form = jQuery('#fluentform_' + formId).length
                    ? jQuery('#fluentform_' + formId)
                    : jQuery('form[data-form_instance="ff_form_instance_' + formId + '_1"]').first();
                if (!$form.length) return;

                var newData = {};
                var formData = new FormData($form[0]);
                formData.forEach(function (value, key) {
                    var k = key.replace(/^names\[/, '').replace(/^custom_values\[/, '').replace(/\]$/, '');
                    if (typeof value === 'string' && value && k.charAt(0) !== '_') {
                        newData[k.toLowerCase()] = value;
                    }
                });

                Object.assign(data, newData);
                applyAliases(data);

                // Cookie verversen met token uit response of huidig token
                var responseToken = (payload && payload.result && payload.result.psfid_token) ||
                                    (payload && payload.psfid_token) ||
                                    config.token;
                if (responseToken) {
                    config.token = responseToken;
                    setCookie(config.cookieName, responseToken, config.cookieDays);
                }
                fillShortcodes();
            } catch (err) { /* silent */ }
        });
    }

    // Gravity Forms succes-event (custom event toegevoegd door integratie)
    document.addEventListener('gform_confirmation_loaded', function (e) {
        if (e.detail && e.detail.token) {
            config.token = e.detail.token;
            setCookie(config.cookieName, e.detail.token, config.cookieDays);
        }
        if (e.detail && e.detail.fields) {
            Object.assign(data, e.detail.fields);
            applyAliases(data);
            fillShortcodes();
        }
    });

    /* ---------- GF auto-resume voor multi-page Save & Continue ----------
     * Als er een PSFID token is en op de pagina staat een GF formulier,
     * vraag dan via REST of er een GF draft is voor deze bezoeker. Zo ja:
     * voeg gform_resume_token toe aan URL en herlaad. GF springt dan zelf
     * naar de juiste pagina van het multi-page formulier.
     *
     * Server-side wordt dit ook gedetecteerd voor klassieke shortcodes,
     * maar deze JS-fallback dekt Gutenberg blocks en dynamisch ingevoegde
     * formulieren waar de server de form-ID niet uit post_content kon halen.
     */
    function gfAutoResume() {
        // Skip als er al een resume-parameter in URL staat (beide namen checken)
        var qs = window.location.search;
        if (qs.indexOf('gf_token=') !== -1 || qs.indexOf('gform_resume_token=') !== -1) return;
        if (!config.token) return;

        // Zoek alle GF formulieren in de DOM
        var gfForms = document.querySelectorAll('form[id^="gform_"]');
        if (gfForms.length === 0) return;

        var match = gfForms[0].id.match(/^gform_(\d+)/);
        if (!match) return;
        var formId = match[1];

        // Zelfde-tab guard
        var guardKey = 'psfid_resume_checked_' + formId;
        try {
            if (sessionStorage.getItem(guardKey)) return;
            sessionStorage.setItem(guardKey, '1');
        } catch (e) { /* sessionStorage not available */ }

        fetch(config.restUrl + 'gf-draft/' + encodeURIComponent(config.token) + '/' + formId)
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (json) {
                if (json && json.uuid) {
                    // Gebruik gf_token (modern) + gform_resume_token (legacy fallback)
                    var url = new URL(window.location.href);
                    url.searchParams.set('gf_token', json.uuid);
                    url.searchParams.set('gform_resume_token', json.uuid);
                    console.log('[PSFID] Resuming GF form ' + formId + ' via draft ' + json.uuid.substring(0, 12));
                    window.location.replace(url.toString());
                }
            })
            .catch(function () { /* silent */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init();
            gfAutoResume();
        });
    } else {
        init();
        gfAutoResume();
    }
})();
