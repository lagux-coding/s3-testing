jQuery(document).ready(function ($) {
    //change size of thickbox
    s3testing_tb_position = function() {
        var tbWindow = $('#TB_window'), width = $(window).width(), height = $(window).height(), W = ( 720 < width ) ? 720 : width,  H = ( 525 < height ) ? 525 : height, adminbar_height = 0;

        if ( tbWindow.length > 0 ) {
            tbWindow.width( W - 50 ).height( H - 45 - adminbar_height );
            $('#TB_iframeContent').width( W - 50 ).height( H - 75 - adminbar_height );
            tbWindow.css({'margin-left': '-' + parseInt((( W - 50 ) / 2),10) + 'px'});
            if ( typeof document.body.style.maxWidth != 'undefined' )
                tbWindow.css({'top': 20 + adminbar_height + 'px','margin-top':'0'});
        }

        return $('a.thickbox').each( function() {
            var href = $(this).attr('href');
            if ( ! href )
                return;
            href = href.replace(/&width=[0-9]+/g, '');
            href = href.replace(/&height=[0-9]+/g, '');
            $(this).attr( 'href', href + '&width=' + ( W - 80 ) + '&height=' + ( H - 85 - adminbar_height ) );
        });
    };

    $(window).resize(function(){ s3testing_tb_position(); });
    s3testing_tb_position();
})