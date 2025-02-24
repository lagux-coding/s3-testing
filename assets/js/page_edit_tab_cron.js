jQuery(document).ready(function ($) {
    $('input[name="activetype"]').change(function () {
        if ($(this).val() == 'wpcron' || $(this).val() == 'easycron') {
            $('.wpcron').show();
            cronstamp();
        } else {
            $('.wpcron').hide();
        }
    });

    if ($('input[name="activetype"]:checked').val() == 'wpcron' || $('input[name="activetype"]:checked').val() == 'easycron' ) {
        $('.wpcron').show();
        cronstamp();
    } else {
        $('.wpcron').hide();
    }

    function cronstamp() {
        var cron_interval = $('select[name="cron_interval"]').val();
        var data = {
            action: 's3testing_cron_text',
            cron_interval: cron_interval,
            _ajax_nonce: $('#s3testingajaxnonce').val()
        };
        console.log(data);
        $.post(ajaxurl, data, function (response) {
            $('#schedulecron').replaceWith(response);
        })
    }
    $('#cron_interval').change(function () {
        cronstamp();
    });
});