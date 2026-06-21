/**
 * pcwithdrawal — front-end JavaScript
 * PHP 5.6 compatible module; this JS requires no transpilation.
 * Vanilla JS only (no jQuery dependency assumed).
 */
(function () {
    'use strict';

    /**
     * Toggle visibility of partial-withdrawal item selection.
     */
    function initScopeToggle() {
        var scopeRadios = document.querySelectorAll('[name="pcwithdrawal_scope"]');
        var itemsBlock  = document.getElementById('pcwithdrawal-items-block');

        if (!scopeRadios.length || !itemsBlock) {
            return;
        }

        function onScopeChange() {
            var selected = document.querySelector('[name="pcwithdrawal_scope"]:checked');
            if (selected && selected.value === 'partial') {
                itemsBlock.style.display = '';
            } else {
                itemsBlock.style.display = 'none';
            }
        }

        for (var i = 0; i < scopeRadios.length; i++) {
            scopeRadios[i].addEventListener('change', onScopeChange);
        }

        // Run once on load
        onScopeChange();
    }

    /**
     * Basic client-side quantity validation for partial withdrawals.
     */
    function initQuantityValidation() {
        var form = document.getElementById('pcwithdrawal-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (e) {
            var scopeInput = document.querySelector('[name="pcwithdrawal_scope"]:checked');
            if (!scopeInput || scopeInput.value !== 'partial') {
                return;
            }

            var qtyInputs  = form.querySelectorAll('[name^="pcwithdrawal_qty_"]');
            var anyChecked = false;

            for (var i = 0; i < qtyInputs.length; i++) {
                if (parseInt(qtyInputs[i].value, 10) > 0) {
                    anyChecked = true;
                    break;
                }
            }

            if (!anyChecked) {
                e.preventDefault();
                var msgEl = document.getElementById('pcwithdrawal-qty-error');
                if (msgEl) {
                    msgEl.style.display = '';
                }
            }
        });
    }

    // Boot when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initScopeToggle();
            initQuantityValidation();
        });
    } else {
        initScopeToggle();
        initQuantityValidation();
    }
}());
