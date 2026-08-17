/**
 *  @name Date (dd/mm/YYYY hh:ii[:ss])
 *  @summary Sort date / time in the format `dd/mm/YYYY hh:ii[:ss]`
 *  Seconds are optional
 */

 jQuery.extend( jQuery.fn.dataTableExt.oSort, {
    "time-br-pre": function ( a ) {
        var x;

        if ( $.trim(a) !== '' ) {
            var frTimea = $.trim(a).split(':');

            if (frTimea[2]) x = (frTimea[0] + frTimea[1] + frTimea[2]) * 1;
            else            x = (frTimea[0] + frTimea[1]) * 1;
        }
        else {
            x = Infinity;
        }

        return x;
    },

    "time-br-asc": function ( a, b ) {
        return a - b;
    },

    "time-br-desc": function ( a, b ) {
        return b - a;
    }
} );