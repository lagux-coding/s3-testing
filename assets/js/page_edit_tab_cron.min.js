jQuery(document).ready(function ($) {
    $('input[name="activetype"]').change(function () {
        if ($(this).val() == 'wpcron' || $(this).val() == 'easycron') {
            $('.wpcron').show();
        } else {
            $('.wpcron').hide();
        }
    });

    if ($('input[name="activetype"]:checked').val() == 'wpcron' || $('input[name="activetype"]:checked').val() == 'easycron' ) {
        $('.wpcron').show();
    } else {
        $('.wpcron').hide();
    }
});