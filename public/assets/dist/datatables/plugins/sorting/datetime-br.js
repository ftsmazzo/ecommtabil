/**
 *  @name Date (dd/mm/YYYY hh:ii[:ss])
 *  @summary Sort date / time in the format `dd/mm/YYYY hh:ii[:ss]`
 *  Seconds are optional
 */

 jQuery.extend( jQuery.fn.dataTableExt.oSort, {
    "datetime-br-pre": function ( a ) {
        var x;

        if ( $.trim(a) !== '' ) {
            var frDatea = $.trim(a).split(' ');
            var frTimea = frDatea[1].split(':');
            var frDatea2 = frDatea[0].split('/');

            if (frTimea[2]) x = (frDatea2[2] + frDatea2[1] + frDatea2[0] + frTimea[0] + frTimea[1] + frTimea[2]) * 1;
            else            x = (frDatea2[2] + frDatea2[1] + frDatea2[0] + frTimea[0] + frTimea[1]) * 1;
        }
        else {
            x = Infinity;
        }

        return x;
    },

    "datetime-br-asc": function ( a, b ) {
        return a - b;
    },

    "datetime-br-desc": function ( a, b ) {
        return b - a;
    }
} );