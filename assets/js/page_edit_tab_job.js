function date(format, timestamp)
{
    var that = this,
        jsdate,
        f,
        formatChr = /\\?([a-z])/gi,
        formatChrCb,
        _pad = function (n, c) {
            n = n.toString();
            return n.length < c ? _pad('0' + n, c, '0') : n;
        };
    formatChrCb = function (t, s) {
        return f[t] ? f[t]() : s;
    };

    f = {
        // Day
        d: function () { // Day of month w/leading 0; 01..31
            return _pad(f.j(), 2);
        },
        j: function () { // Day of month; 1..31
            return jsdate.getDate();
        },

        // Month
        m: function () { // Month w/leading 0; 01...12
            return _pad(f.n(), 2);
        },
        n: function () { // Month; 1...12
            return jsdate.getMonth() + 1;
        },

        // Year
        Y: function () {
            return jsdate.getFullYear();
        },

        // Hour
        G: function () { // 24-Hours; 0..23
            return jsdate.getHours();
        },
        H: function () { // 24-Hours w/leading 0; 00..23
            return _pad(f.G(), 2);
        },

        // Minute
        i: function () {
            return _pad(jsdate.getMinutes(), 2);
        },

        // Second
        s: function () {
            return _pad(jsdate.getSeconds(), 2);
        },
    };

    this.date = function(format, timestamp) {
        that = this;
        jsdate = (timestamp === undefined ? new Date() : (timestamp instanceof Date) ? new Date(timestamp) : new Date(timestamp * 1000));
        return format.replace(formatChr, formatChrCb);
    }
    return this.date(format, timestamp);
}

jQuery(document).ready(function ($) {
    $('input[name="type[]"]').change(function () {
        if($('input[name="type[]"]:checked').hasClass('filetype')) {
            $('.hasdests').show();
        } else {
            $('.hasdests').hide();
        }
        $( '#tab-jobtype-' + $(this).val().toLowerCase() ).toggle( );
    });

    if ($('input[name="type[]"]:checked').hasClass('filetype')) {
        $('.hasdests').show();
    } else {
        $('.hasdests').hide();
    }

    $('input[name="destinations[]"]').change(function () {
        $( '#tab-dest-' + $(this).val().toLowerCase() ).toggle( );
    });

    $('input[name="name"]').keyup(function () {
        $('#h2jobtitle').replaceWith('<span id="h2jobtitle">' + s3testing_htmlspecialchars( $(this).val() ) + '</span>');
    });

    $('input[name="name"]').focus( function () {
        if ( $(this).val() == $(this).data( 'empty' ) ) {
            $(this).val( '' );
        }
    });

    $('input[name="name"]').blur( function () {
        if ( $(this).val() === '' ) {
            $(this).val( $(this).data( 'empty' ) );
        }
    });

    $('input[name="archiveformart"]').change(function () {
        $('#archiveformart').replaceWith('<span id="archiveformart">' + $(this).val() + '</span>');
    });

    $('input[name="archivename"]').keyup(function () {
        var filename = $(this).val();
        filename = filename.replace( '%hash%', '[hash]' );
        filename = filename.replace( '%d', date( 'd' ) );
        filename = filename.replace( '%m', date( 'm' ) );
        filename = filename.replace( '%Y', date( 'Y' ) );
        filename = filename.replace( '%H', date( 'H' ) );
        filename = filename.replace( '%i', date( 'i' ) );
        filename = filename.replace( '%s', date( 's' ) );
        filename = filename.replace( '[hash]', '%hash%' );
        $('#archivefilename').replaceWith('<span id="archivefilename">' + filename + '</span>');
    });
});