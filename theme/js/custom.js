/**
 * LimeSurvey Custom JavaScript
 * =============================
 * Place this file in your extended theme at: js/custom.js
 * See README.md for full setup instructions.
 *
 * Features:
 *   - Dynamic "Add another item" button for Multiple Short Text questions
 *
 * Usage:
 *   In the question editor, go to the Display tab and add the CSS class:
 *     dynamic-add-list
 *   The question will then show one input at a time with a button to reveal more.
 *   The maximum number of items is controlled by how many sub-questions you define.
 */

$(document).on('ready pjax:complete', function () {

    // Only affect questions that have the 'dynamic-add-list' CSS class
    $('.dynamic-add-list ul.subquestion-list').each(function () {
        var $ul = $(this);
        var $rows = $ul.find('li[id^="javatbd"]');

        // Skip if only one item or already initialised
        if ($rows.length <= 1) return;
        if ($ul.data('dyn-init')) return;
        $ul.data('dyn-init', true);

        // Hide all rows after the first
        $rows.each(function (i) {
            if (i > 0) $(this).hide().addClass('ls-dyn-hidden');
        });

        // Inject the Add button below the list
        var $btn = $('<button type="button" class="btn btn-primary btn-lg" style="margin-top:10px;">'
            + '<span style="font-size:1.2em;margin-right:5px;">+</span>Add another item'
            + '</button>');
        $ul.after($btn);

        // Scoped click handler per list so multiple questions don't share state
        (function ($r, $b) {
            $b.on('click', function (e) {
                e.preventDefault();
                var $next = $r.filter('.ls-dyn-hidden').first();
                if ($next.length) {
                    $next.removeClass('ls-dyn-hidden').show();
                    $next.find('input[type="text"]').focus();
                }
                if ($r.filter('.ls-dyn-hidden').length === 0) {
                    $b.prop('disabled', true).text('✓ Maximum items reached');
                }
            });
        })($rows, $btn);
    });

});
