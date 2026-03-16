/******************
    User custom JS
    ---------------

   Put JS-functions for your template here.
   If possible use a closure, or add them to the general Template Object "Template"
*/
$(document).on('ready pjax:complete', function () {
    $('.dynamic-add-list ul.subquestion-list').each(function () {
        var $ul = $(this);
        var $rows = $ul.find('li[id^="javatbd"]');

        if ($rows.length <= 1) return;

        // Don't re-initialise if already set up
        if ($ul.data('dyn-init')) return;
        $ul.data('dyn-init', true);

        $rows.each(function (i) {
            if (i > 0) $(this).hide().addClass('ls-dyn-hidden');
        });

        var $btn = $('<button type="button" class="btn btn-primary btn-lg" style="margin-top:10px;"><span style="font-size:1.2em;margin-right:5px;">+</span>Add another item</button>');
        $ul.after($btn);

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