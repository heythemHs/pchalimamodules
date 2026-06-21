/**
 * pcwithdrawal — front-end JavaScript
 * PHP 5.6 compatible module; this JS requires no transpilation.
 * Vanilla JS only — no jQuery dependency.
 *
 * Features:
 *  - Scope toggle: show/hide item list based on full/partial selection
 *  - Quantity validation: min=1 max={ordered} per item; checkbox-driven
 *  - Select-all checkbox for item list
 *  - Print handler for success page
 *  - Auto-submit form on verify page when token is in URL
 *  - Dirty-check warning before navigating away from the review form
 */
(function () {
    'use strict';

    /* ------------------------------------------------------------------
       Scope toggle — show/hide product list based on scope radio value
       ------------------------------------------------------------------ */
    function initScopeToggle() {
        var scopeRadios = document.querySelectorAll('[name="pcwdl_scope"]');
        var itemsBlock  = document.getElementById('pcwithdrawal-items-block');

        if (!scopeRadios.length || !itemsBlock) {
            return;
        }

        function applyScope() {
            var selected = document.querySelector('[name="pcwdl_scope"]:checked');
            if (selected && selected.value === 'partial') {
                itemsBlock.style.display = '';
                itemsBlock.removeAttribute('aria-hidden');
            } else {
                itemsBlock.style.display = 'none';
                itemsBlock.setAttribute('aria-hidden', 'true');
                // Reset all qty inputs when switching back to full
                var qtyInputs = itemsBlock.querySelectorAll('.pcwithdrawal-qty-input');
                for (var i = 0; i < qtyInputs.length; i++) {
                    qtyInputs[i].value = '0';
                }
                var checkboxes = itemsBlock.querySelectorAll('.pcwithdrawal-item-checkbox');
                for (var j = 0; j < checkboxes.length; j++) {
                    checkboxes[j].checked = false;
                }
            }
        }

        for (var i = 0; i < scopeRadios.length; i++) {
            scopeRadios[i].addEventListener('change', applyScope);
        }

        applyScope();
    }

    /* ------------------------------------------------------------------
       Item checkboxes — drive qty input enable/disable and value
       ------------------------------------------------------------------ */
    function initItemCheckboxes() {
        var itemsBlock = document.getElementById('pcwithdrawal-items-block');
        if (!itemsBlock) {
            return;
        }

        var checkboxes = itemsBlock.querySelectorAll('.pcwithdrawal-item-checkbox');

        function onCheckboxChange(checkbox) {
            var qtyInputId = checkbox.getAttribute('data-qty-input');
            if (!qtyInputId) {
                return;
            }
            var qtyInput = document.getElementById(qtyInputId);
            if (!qtyInput) {
                return;
            }
            if (checkbox.checked) {
                var max = parseInt(qtyInput.getAttribute('data-max') || '1', 10);
                qtyInput.value = max > 0 ? max : 1;
            } else {
                qtyInput.value = '0';
            }
        }

        for (var i = 0; i < checkboxes.length; i++) {
            (function (cb) {
                cb.addEventListener('change', function () {
                    onCheckboxChange(cb);
                    updateSelectAllState();
                });
            }(checkboxes[i]));
        }
    }

    /* ------------------------------------------------------------------
       Quantity validation — enforce min 0 (unchecked), min 1 when checked
       ------------------------------------------------------------------ */
    function initQuantityValidation() {
        var form = document.getElementById('pcwdl-select-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (e) {
            var scopeInput = document.querySelector('[name="pcwdl_scope"]:checked');
            if (!scopeInput || scopeInput.value !== 'partial') {
                return; // full order — no item validation needed
            }

            var qtyInputs  = form.querySelectorAll('.pcwithdrawal-qty-input');
            var totalWithdrawn = 0;

            for (var i = 0; i < qtyInputs.length; i++) {
                var val = parseInt(qtyInputs[i].value, 10);
                var max = parseInt(qtyInputs[i].getAttribute('data-max') || '0', 10);

                if (isNaN(val) || val < 0) {
                    qtyInputs[i].value = '0';
                    val = 0;
                }
                if (val > max) {
                    qtyInputs[i].value = String(max);
                    val = max;
                }
                totalWithdrawn += val;
            }

            if (totalWithdrawn < 1) {
                e.preventDefault();
                var msgEl = document.getElementById('pcwdl-qty-error');
                if (msgEl) {
                    msgEl.style.display = '';
                    msgEl.setAttribute('aria-live', 'assertive');
                    // Scroll into view
                    if (msgEl.scrollIntoView) {
                        msgEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
            } else {
                var msgEl2 = document.getElementById('pcwdl-qty-error');
                if (msgEl2) {
                    msgEl2.style.display = 'none';
                }
            }
        });

        // Also validate individual qty inputs on blur
        var qtyInputs = form.querySelectorAll('.pcwithdrawal-qty-input');
        for (var i = 0; i < qtyInputs.length; i++) {
            (function (input) {
                input.addEventListener('change', function () {
                    var val = parseInt(input.value, 10);
                    var max = parseInt(input.getAttribute('data-max') || '0', 10);
                    if (isNaN(val) || val < 0) { input.value = '0'; }
                    if (val > max)              { input.value = String(max); }

                    // Sync the associated checkbox
                    var qtyId    = input.id;
                    var checkbox = document.querySelector('[data-qty-input="' + qtyId + '"]');
                    if (checkbox) {
                        checkbox.checked = parseInt(input.value, 10) > 0;
                        updateSelectAllState();
                    }
                });
            }(qtyInputs[i]));
        }
    }

    /* ------------------------------------------------------------------
       Select-all checkbox
       ------------------------------------------------------------------ */
    function initSelectAll() {
        var selectAll  = document.getElementById('pcwdl-select-all');
        var itemsBlock = document.getElementById('pcwithdrawal-items-block');

        if (!selectAll || !itemsBlock) {
            return;
        }

        selectAll.addEventListener('change', function () {
            var checkboxes = itemsBlock.querySelectorAll('.pcwithdrawal-item-checkbox');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = selectAll.checked;

                var qtyInputId = checkboxes[i].getAttribute('data-qty-input');
                if (qtyInputId) {
                    var qtyInput = document.getElementById(qtyInputId);
                    if (qtyInput) {
                        if (selectAll.checked) {
                            var max = parseInt(qtyInput.getAttribute('data-max') || '1', 10);
                            qtyInput.value = max > 0 ? max : 1;
                        } else {
                            qtyInput.value = '0';
                        }
                    }
                }
            }
        });
    }

    function updateSelectAllState() {
        var selectAll  = document.getElementById('pcwdl-select-all');
        var itemsBlock = document.getElementById('pcwithdrawal-items-block');

        if (!selectAll || !itemsBlock) {
            return;
        }

        var checkboxes = itemsBlock.querySelectorAll('.pcwithdrawal-item-checkbox');
        var totalChecked = 0;

        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                totalChecked++;
            }
        }

        selectAll.checked       = (totalChecked === checkboxes.length && checkboxes.length > 0);
        selectAll.indeterminate = (totalChecked > 0 && totalChecked < checkboxes.length);
    }

    /* ------------------------------------------------------------------
       Auto-submit on verify page when token arrives via GET
       ------------------------------------------------------------------ */
    function initAutoSubmit() {
        var autoForm = document.getElementById('pcwdl-auto-verify-form');
        if (!autoForm) {
            return;
        }

        // Small delay to allow the browser to render the "please wait" message
        setTimeout(function () {
            autoForm.submit();
        }, 300);
    }

    /* ------------------------------------------------------------------
       Dirty-check on review page — warn before navigation away
       ------------------------------------------------------------------ */
    function initDirtyCheck() {
        var form = document.getElementById('pcwdl-confirm-form');
        if (!form || !form.getAttribute('data-dirty-check')) {
            return;
        }

        var submitted = false;

        form.addEventListener('submit', function () {
            submitted = true;
        });

        window.addEventListener('beforeunload', function (e) {
            if (submitted) {
                return;
            }
            // Only warn if the user is actually on the review page with the form visible
            if (document.getElementById('pcwdl-confirm-form')) {
                var msg = 'Your withdrawal declaration has not been submitted yet. Are you sure you want to leave?';
                e.returnValue = msg;
                return msg;
            }
        });

        // The "go back" link on the review page is intentional — suppress the warning
        var backLink = document.getElementById('pcwdl-back-link');
        if (backLink) {
            backLink.addEventListener('click', function () {
                submitted = true; // treat back-navigation as intentional
            });
        }
    }

    /* ------------------------------------------------------------------
       Print handler (success page) — supplemental to the inline onclick
       ------------------------------------------------------------------ */
    function initPrintButton() {
        var btn = document.getElementById('pcwdl-print-btn');
        if (!btn) {
            return;
        }
        // The button already has onclick="window.print()" in the template.
        // This handler adds keyboard accessibility for Enter/Space.
        btn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                window.print();
            }
        });
    }

    /* ------------------------------------------------------------------
       Boot
       ------------------------------------------------------------------ */
    function boot() {
        initScopeToggle();
        initItemCheckboxes();
        initQuantityValidation();
        initSelectAll();
        initAutoSubmit();
        initDirtyCheck();
        initPrintButton();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
