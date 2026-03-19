/******************
    User custom JS
    ---------------

   Put JS-functions for your template here.
   If possible use a closure, or add them to the general Template Object "Template"
*/
/**
 * LimeSurvey Custom JavaScript
 * =============================
 * File: theme/scripts/custom.js
 * See README.md for setup instructions.
 *
 * Features:
 *   - Email validation with inline error styling for Multiple Short Text questions
 *
 * Note: Dynamic "Add another item" behaviour is handled natively by LimeSurvey's
 *   built-in "Input on demand" question type — no custom JavaScript needed for that.
 *
 * ─── Contact Info Validation ─────────────────────────────────────────────────
 * Applies to any Multiple Short Text question with CSS class: contact-info
 *
 * Setup in LimeSurvey question editor:
 *   1. Question Display tab → CSS class field → enter: contact-info
 *   2. Set each subquestion's Code field (not label) as follows:
 *        fullname  — validated as non-empty
 *        email     — validated as a properly formatted email address
 *
 * Validation fires on blur (when the field loses focus).
 * Errors clear automatically once the field has a valid value.
 */

$(document).on('ready pjax:complete', function () {

    var $question = $('.contact-info');
    if ($question.length === 0) return;

    // ── Helpers ──────────────────────────────────────────────────────────────

    function makeError(msg) {
        return $(
            '<div class="ls-field-error" style="'
            + 'color:#cc0000;'
            + 'font-size:0.9em;'
            + 'margin-top:5px;'
            + 'padding:8px 12px;'
            + 'background:#fde8e8;'
            + 'border-left:4px solid #cc0000;'
            + 'border-radius:3px;'
            + 'display:none;'
            + '">' + msg + '</div>'
        );
    }

    function setError($input, $msg) {
        $input.closest('li').css('background', '#fde8e8');
        $input.css({'border-color': '#cc0000', 'outline-color': '#cc0000'});
        $msg.show();
    }

    function clearError($input, $msg) {
        $input.closest('li').css('background', '');
        $input.css({'border-color': '', 'outline-color': ''});
        $msg.hide();
    }

    // ── Full name — non-empty check ───────────────────────────────────────────

    var $nameInput = $question.find('input[type="text"][name$="fullname"]');
    if ($nameInput.length) {
        var $nameError = makeError('Please enter a name');
        $nameInput.closest('li').append($nameError);

        $nameInput.on('blur', function () {
            if ($nameInput.val().trim() === '') {
                setError($nameInput, $nameError);
            } else {
                clearError($nameInput, $nameError);
            }
        });

        $nameInput.on('input', function () {
            if ($nameError.is(':visible') && $nameInput.val().trim() !== '') {
                clearError($nameInput, $nameError);
            }
        });
    }

    // ── Email — regex check ───────────────────────────────────────────────────
    // Use RegExp constructor to avoid LimeSurvey ExpressionScript interference
    // with curly-brace syntax. The pattern requires at least a two-letter TLD.

    var $emailInput = $question.find('input[type="text"][name$="email"]');
    if ($emailInput.length) {
        var $emailError = makeError('Please enter a valid email address');
        $emailInput.closest('li').append($emailError);

        var emailRegex = new RegExp('^[a-zA-Z0-9._%+\\-]+@[a-zA-Z0-9.\\-]+\\.[a-zA-Z][a-zA-Z]+$');

        $emailInput.on('blur', function () {
            var val = $emailInput.val().trim();
            if (val && !emailRegex.test(val)) {
                setError($emailInput, $emailError);
            } else {
                clearError($emailInput, $emailError);
            }
        });

        $emailInput.on('input', function () {
            if ($emailError.is(':visible') && emailRegex.test($emailInput.val().trim())) {
                clearError($emailInput, $emailError);
            }
        });
    }

});
