/* Pure Smart Form ID - Admin JS */
(function ($) {
    'use strict';

    if (typeof PSFID_ADMIN === 'undefined') return;

    /* Provider switch */
    $('#psfid-crm-provider').on('change', function () {
        var v = $(this).val();
        $('#psfid-ac-settings').toggle(v === 'activecampaign');
        $('#psfid-fcrm-info').toggle(v === 'fluentcrm');
    });

    /* AC test */
    $('#psfid-ac-test').on('click', function () {
        var $btn = $(this);
        var $result = $('#psfid-ac-test-result');
        var url = $('#psfid-ac-url').val();
        var key = $('#psfid-ac-key').val();

        if (!url || !key) {
            $result.removeClass('psfid-ok').addClass('psfid-fail').text(PSFID_ADMIN.i18n.fill_url_key);
            return;
        }

        $btn.prop('disabled', true);
        $result.removeClass('psfid-ok psfid-fail').text(PSFID_ADMIN.i18n.testing);

        $.post(PSFID_ADMIN.ajaxUrl, {
            action: 'psfid_test_ac',
            nonce: PSFID_ADMIN.nonce,
            url: url,
            key: key
        }).done(function (resp) {
            if (resp.success) {
                $result.addClass('psfid-ok').text(PSFID_ADMIN.i18n.connected_as + ' ' + resp.data.message);
            } else {
                $result.addClass('psfid-fail').text(PSFID_ADMIN.i18n.failed + ' ' + (resp.data || PSFID_ADMIN.i18n.unknown_error));
            }
        }).fail(function () {
            $result.addClass('psfid-fail').text(PSFID_ADMIN.i18n.connection_failed);
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* Token verwijderen (zowel detail-page als list-row) */
    $(document).on('click', '.psfid-delete-token', function (e) {
        e.preventDefault();
        if (!confirm(PSFID_ADMIN.i18n.confirm_delete)) return;
        var id = $(this).data('token-id');
        var $link = $(this);
        var $row = $link.closest('tr');
        var isListRow = $row.length && $row.find('code').length > 0;

        $.post(PSFID_ADMIN.ajaxUrl, {
            action: 'psfid_delete_token',
            nonce: PSFID_ADMIN.nonce,
            token_id: id
        }).done(function (resp) {
            if (resp.success) {
                if (isListRow) {
                    // Inline: fade de rij weg
                    $row.css('background-color', '#fde7e7').fadeOut(300, function () {
                        $row.remove();
                    });
                } else {
                    // Detail-pagina: terug naar overzicht
                    window.location.href = window.location.pathname + '?page=psfid-tokens';
                }
            } else {
                alert(PSFID_ADMIN.i18n.failed + ' ' + (resp.data || PSFID_ADMIN.i18n.unknown_error));
            }
        }).fail(function () {
            alert(PSFID_ADMIN.i18n.connection_failed);
        });
    });

    /* Add alias rows dynamically */
    $(document).on('click', '.psfid-add-row', function () {
        var $table = $($(this).data('target'));
        var $tbody = $table.find('tbody');
        var lastRow = $tbody.find('tr:last');
        var newRow = lastRow.clone();
        var idx = $tbody.find('tr').length - 1;

        newRow.find('input').each(function () {
            var name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace(/\[\d+\]/, '[' + idx + ']'));
            }
            $(this).val('');
        });

        // Convert "Add row" to "Remove" on the previous row
        lastRow.find('.psfid-add-row').replaceWith('<button type="button" class="button psfid-remove-row">' + PSFID_ADMIN.i18n.remove + '</button>');
        $tbody.append(newRow);
    });

    $(document).on('click', '.psfid-remove-row', function () {
        $(this).closest('tr').remove();
    });

    /* Help TOC: active state on scroll */
    var $tocLinks = $('.psfid-help-toc a');
    if ($tocLinks.length) {
        var sections = [];
        $tocLinks.each(function () {
            var id = $(this).attr('href');
            if (id && id.charAt(0) === '#') {
                var $sec = $(id);
                if ($sec.length) {
                    sections.push({ id: id, top: $sec.offset().top });
                }
            }
        });

        function updateActiveTocLink() {
            var scrollTop = $(window).scrollTop() + 80;
            var current = sections[0] ? sections[0].id : null;
            for (var i = 0; i < sections.length; i++) {
                if (sections[i].top <= scrollTop) {
                    current = sections[i].id;
                }
            }
            $tocLinks.removeClass('is-active');
            if (current) {
                $tocLinks.filter('[href="' + current + '"]').addClass('is-active');
            }
        }

        $(window).on('scroll', updateActiveTocLink);
        updateActiveTocLink();
    }

})(jQuery);
