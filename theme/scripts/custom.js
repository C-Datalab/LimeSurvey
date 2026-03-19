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
 * ─── Email Validation ────────────────────────────────────────────────────────
 * Automatically applies to any subquestion whose label contains "email",
 * "e-mail", or "メールアドレス" (case-insensitive), in any Multiple Short Text
 * question, on any survey using this theme.
 *
 * No CSS class or subquestion code convention required — detection is
 * entirely label-based. Non-empty validation for other fields (e.g. name)
 * is handled by LimeSurvey's native mandatory question setting.
 *
 * Validation fires on blur. Error clears as soon as the value becomes valid.
 */

$(document).on('ready pjax:complete', function () {

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

    // ── Email — label-based detection + regex check ───────────────────────────
    // Matches labels containing "email", "e-mail", or "メールアドレス".
    // Use RegExp constructor to avoid LimeSurvey ExpressionScript interference
    // with curly-brace syntax. The pattern requires at least a two-letter TLD.

    var emailLabelRegex = /e[\-\s]?mail|メールアドレス/i;
    var emailRegex = new RegExp('^[a-zA-Z0-9._%+\\-]+@[a-zA-Z0-9.\\-]+\\.[a-zA-Z][a-zA-Z]+$');

    $('.subquestion-list li').each(function () {
        var $li = $(this);
        var labelText = $li.find('label').text().trim();
        if (!emailLabelRegex.test(labelText)) return;

        var $input = $li.find('input[type="text"]');
        if (!$input.length) return;

        // Guard against double-initialisation on pjax reloads
        if ($input.data('ls-email-init')) return;
        $input.data('ls-email-init', true);

        var $error = makeError('Please enter a valid email address');
        $li.append($error);

        $input.on('blur', function () {
            var val = $input.val().trim();
            if (val && !emailRegex.test(val)) {
                setError($input, $error);
            } else {
                clearError($input, $error);
            }
        });

        $input.on('input', function () {
            if ($error.is(':visible') && emailRegex.test($input.val().trim())) {
                clearError($input, $error);
            }
        });
    });

});
