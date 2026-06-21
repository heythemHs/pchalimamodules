/**
 * pcwithdrawal — Template editor TinyMCE integration.
 * Initializes TinyMCE if available; falls back to plain textarea.
 * Manages visual/source toggle and placeholder insertion at cursor.
 */

(function ($) {
    'use strict';

    var editorInitialized = false;
    var useVisualMode     = false;
    var editorId          = 'pcw-html-editor';

    /* ============================================================
       TinyMCE initialization
    ============================================================ */
    function initTinyMCE() {
        if (typeof window.tinymce === 'undefined') {
            // TinyMCE not available — use plain textarea
            console.log('[pcwithdrawal] TinyMCE not detected, using plain textarea.');
            return false;
        }

        tinymce.init({
            selector:  '#' + editorId,
            height:    500,
            menubar:   false,
            plugins:   'link lists code table image',
            toolbar:   'undo redo | bold italic underline | bullist numlist | link image table | code',
            content_css: false,
            branding:  false,
            sandbox_iframes: true,
            // Prevent script execution in preview
            extended_valid_elements: '',
            invalid_elements: 'script,iframe,frame,frameset,object,embed,form',
            setup: function (editor) {
                editor.on('init', function () {
                    editorInitialized = true;
                    useVisualMode     = true;
                    updateToggleButton();
                    console.log('[pcwithdrawal] TinyMCE initialized.');
                });
                editor.on('change keyup', function () {
                    editor.save(); // Sync back to textarea
                    $(document.getElementById(editorId)).trigger('input');
                });
            },
            // Restrict content to safe HTML only
            paste_as_text:         false,
            paste_remove_styles:   false,
            forced_root_block:     'p'
        });

        return true;
    }

    /* ============================================================
       Toggle visual / source mode
    ============================================================ */
    function updateToggleButton() {
        var $btn = $('#pcw-toggle-visual');
        if (useVisualMode) {
            $btn.html('<i class="icon-code"></i> Source');
            $btn.attr('title', 'Switch to source code view');
        } else {
            $btn.html('<i class="icon-eye"></i> Visual');
            $btn.attr('title', 'Switch to visual editor');
        }
    }

    function toggleEditorMode() {
        if (typeof window.tinymce === 'undefined' || !editorInitialized) {
            return;
        }

        var editor = tinymce.get(editorId);
        if (!editor) {
            return;
        }

        if (useVisualMode) {
            // Switch to source
            editor.save();
            tinymce.remove('#' + editorId);
            editorInitialized = false;
            useVisualMode     = false;
            updateToggleButton();
        } else {
            // Switch back to visual
            initTinyMCE();
            useVisualMode = true;
            updateToggleButton();
        }
    }

    /* ============================================================
       Placeholder insertion at TinyMCE cursor or raw textarea
    ============================================================ */
    function insertPlaceholderAt(placeholder) {
        var text = '{{' + placeholder + '}}';

        if (editorInitialized && typeof window.tinymce !== 'undefined') {
            var editor = tinymce.get(editorId);
            if (editor) {
                editor.execCommand('mceInsertContent', false, text);
                editor.save();
                return;
            }
        }

        // Fallback: insert at cursor in raw textarea
        var el = document.getElementById(editorId);
        if (!el) {
            return;
        }

        var start = el.selectionStart;
        var end   = el.selectionEnd;
        var val   = el.value;

        el.value = val.substring(0, start) + text + val.substring(end);
        el.selectionStart = el.selectionEnd = start + text.length;
        el.focus();
        $(el).trigger('input');
    }

    /* ============================================================
       Boot
    ============================================================ */
    $(document).ready(function () {
        // Only run on templates editor page
        if (!$('#pcw-html-editor').length) {
            return;
        }

        // Try TinyMCE
        initTinyMCE();

        // Toggle button
        $('#pcw-toggle-visual').on('click', function () {
            toggleEditorMode();
        });

        // Placeholder insertion from sidebar (override general handler)
        $('.pcw-placeholder-item').off('click').on('click', function () {
            var ph = $(this).data('placeholder');
            if (ph) {
                insertPlaceholderAt(ph);
            }
        });

        // Sync TinyMCE content before form submit
        $('#pcw-template-form').on('submit', function () {
            if (editorInitialized && typeof window.tinymce !== 'undefined') {
                var editor = tinymce.get(editorId);
                if (editor) {
                    editor.save();
                }
            }
        });
    });

}(jQuery));
