/**
 * pcwithdrawal — Admin back-office JavaScript.
 * No external dependencies beyond jQuery (provided by PrestaShop admin).
 * PHP 5.6 / PS 1.7+ compatible (no ES6 class syntax, no arrow functions in jQuery context).
 */

(function ($) {
    'use strict';

    /* ============================================================
       Settings tabs — persist active tab via URL hash
    ============================================================ */
    function initSettingsTabs() {
        var hash = window.location.hash;
        if (hash && $(hash).length) {
            $('.pcw-settings-tabs a[href="' + hash + '"]').tab('show');
        }
        $('.pcw-settings-tabs a').on('click', function () {
            history.replaceState(null, null, $(this).attr('href'));
        });
    }

    /* ============================================================
       Status Change Form — show/hide reason based on transition
    ============================================================ */
    function initStatusChangeForm() {
        var $select    = $('#pcw-new-status-select');
        var $reasonGrp = $('#pcw-reason-group');
        var $reasonReq = $('#pcw-reason-required');
        var $reasonTa  = $('#pcw-reason-textarea');
        var $submitBtn = $('#pcw-change-status-btn');

        if (!$select.length) {
            return;
        }

        var reasonsMap = {};
        try {
            reasonsMap = JSON.parse($select.attr('data-reasons-required') || '{}');
        } catch (e) {}

        $select.on('change', function () {
            var ns = $(this).val();
            $submitBtn.prop('disabled', !ns);
            if (!ns) {
                $reasonGrp.hide();
                return;
            }
            $reasonGrp.show();
            if (reasonsMap[ns]) {
                $reasonReq.show();
                $reasonTa.prop('required', true);
            } else {
                $reasonReq.hide();
                $reasonTa.prop('required', false);
            }
        });
    }

    /* ============================================================
       Force-link warning in Order Linking section
    ============================================================ */
    function initForceLinkWarning() {
        $('#pcw-force-link').on('change', function () {
            $('#pcw-force-link-warning').toggle(this.checked);
        });
    }

    /* ============================================================
       Purge confirmation — enable button only when phrase typed
    ============================================================ */
    function initPurgeConfirmation() {
        var $phrase = $('#pcw-purge-phrase');
        var $btn    = $('#pcw-purge-btn');

        if (!$phrase.length || !$btn.length) {
            return;
        }

        $phrase.on('input', function () {
            $btn.prop('disabled', $(this).val() !== 'CONFIRM PURGE');
        });

        $('#pcw-purge-form').on('submit', function (e) {
            if (!window.confirm(pcwAdminVars.confirmPurge || 'Are you absolutely sure?')) {
                e.preventDefault();
            }
        });
    }

    /* ============================================================
       Template editor — placeholder insertion
    ============================================================ */
    function initPlaceholderInsertion() {
        $('.pcw-placeholder-item').on('click', function () {
            var ph   = '{{' + $(this).data('placeholder') + '}}';
            var $ta  = $(document.activeElement);

            // Try to insert at cursor in currently focused textarea
            if ($ta.is('textarea') && $ta.closest('.pcw-templates-panel').length) {
                insertAtCursor($ta[0], ph);
                return;
            }

            // Default: insert into HTML editor
            var el = document.getElementById('pcw-html-editor');
            if (el) {
                insertAtCursor(el, ph);
                el.focus();
            }
        });
    }

    function insertAtCursor(el, text) {
        var start = el.selectionStart;
        var end   = el.selectionEnd;
        var val   = el.value;

        el.value = val.substring(0, start) + text + val.substring(end);
        el.selectionStart = el.selectionEnd = start + text.length;

        // Trigger change event so dirty-state tracking picks it up
        $(el).trigger('input');
    }

    /* ============================================================
       Template editor — Preview button
    ============================================================ */
    function initTemplatePreview() {
        $('#pcw-preview-btn').on('click', function () {
            var subject = $('#pcw-tpl-subject').val();
            var html    = $('#pcw-html-editor').val();
            var action  = $('#pcw-template-form').attr('action');
            var token   = $('input[name="token"]', '#pcw-template-form').val();
            var idLang  = $('input[name="id_lang"]', '#pcw-template-form').val();
            var code    = $('input[name="template_code"]', '#pcw-template-form').val();

            $.post(action, {
                token:          token,
                pcw_action:     'previewTemplate',
                id_lang:        idLang,
                template_code:  code,
                subject:        subject,
                html_content:   html
            }, function (data) {
                if (data && data.html) {
                    var iframe = document.getElementById('pcw-preview-iframe');
                    var doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(data.html);
                    doc.close();
                    $('.pcw-preview-subject-label').text('Subject: ' + (data.subject || ''));
                    $('#pcw-preview-modal').modal('show');
                }
            }, 'json');
        });
    }

    /* ============================================================
       Template editor — Send Test Template
    ============================================================ */
    function initSendTestTemplate() {
        $('#pcw-send-test-btn').on('click', function () {
            var $form   = $('#pcw-template-form');
            var action  = $form.attr('action');
            var subject = $('#pcw-tpl-subject').val();
            var html    = $('#pcw-html-editor').val();
            var text    = $('#pcw-text-editor').val();
            var token   = $('input[name="token"]', $form).val();
            var idLang  = $('input[name="id_lang"]', $form).val();
            var code    = $('input[name="template_code"]', $form).val();

            var $btn = $(this).prop('disabled', true).text('Sending…');

            $.post(action, {
                token:          token,
                pcw_action:     'sendTestTemplate',
                id_lang:        idLang,
                template_code:  code,
                subject:        subject,
                html_content:   html,
                text_content:   text
            }, function () {
                // Page will reload with confirmations/errors from controller
                window.location.reload();
            }).always(function () {
                $btn.prop('disabled', false).text('Send Test Email');
            });
        });
    }

    /* ============================================================
       Template editor — Dirty state tracking
    ============================================================ */
    function initDirtyState() {
        var dirty = false;

        $('#pcw-template-form textarea, #pcw-template-form input[type="text"]').on('input change', function () {
            dirty = true;
        });

        $('#pcw-template-form').on('submit', function () {
            dirty = false;
        });

        $(window).on('beforeunload', function () {
            if (dirty) {
                return 'You have unsaved changes. Leave anyway?';
            }
        });
    }

    /* ============================================================
       Template editor — Unknown placeholder detection
    ============================================================ */
    function initUnknownPlaceholderDetection() {
        var knownList = [];
        $('.pcw-placeholder-item').each(function () {
            knownList.push($(this).data('placeholder'));
        });
        if (!knownList.length) {
            return;
        }

        $('#pcw-html-editor, #pcw-tpl-subject').on('input', function () {
            var val     = $(this).val();
            var matches = val.match(/\{\{([a-z0-9_]+)\}\}/gi) || [];
            var hasUnknown = false;

            for (var i = 0; i < matches.length; i++) {
                var ph = matches[i].replace(/\{\{|\}\}/g, '');
                if (knownList.indexOf(ph) === -1) {
                    hasUnknown = true;
                    break;
                }
            }

            if (hasUnknown) {
                $(this).css('border-color', '#f0ad4e');
                $(this).attr('title', 'Unknown placeholder(s) detected — they will be left as-is');
            } else {
                $(this).css('border-color', '');
                $(this).removeAttr('title');
            }
        });
    }

    /* ============================================================
       Template history expand/collapse
    ============================================================ */
    function initHistoryPanel() {
        $('#pcw-history-heading').on('click', function () {
            var $icon = $(this).find('.icon-chevron-down, .icon-chevron-up');
            $icon.toggleClass('icon-chevron-down icon-chevron-up');
        });
    }

    /* ============================================================
       Order linking — show warning when force-link checked
    ============================================================ */
    function initOrderLinking() {
        $('#pcw-force-link').on('change', function () {
            $('#pcw-force-link-warning').toggle(this.checked);
        });
    }

    /* ============================================================
       Requests list — check all
    ============================================================ */
    function initBulkCheckbox() {
        $('#checkBoxAll').on('change', function () {
            $('input[name="pcw_ids[]"]').prop('checked', this.checked);
        });
    }

    /* ============================================================
       Boot
    ============================================================ */
    $(document).ready(function () {
        initSettingsTabs();
        initStatusChangeForm();
        initForceLinkWarning();
        initPurgeConfirmation();
        initPlaceholderInsertion();
        initTemplatePreview();
        initSendTestTemplate();
        initDirtyState();
        initUnknownPlaceholderDetection();
        initHistoryPanel();
        initOrderLinking();
        initBulkCheckbox();
    });

}(jQuery));
