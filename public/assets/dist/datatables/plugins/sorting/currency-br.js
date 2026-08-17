/**
 * This plug-in will provide numeric sorting for currency columns (either 
 * detected automatically with the currency type detection plug-in or set 
 * manually) while taking account of the currency symbol ($ or £ by default).
 *
 * DataTables 1.10+ has currency sorting abilities built-in and will be
 * automatically detected. As such this plug-in is marked as deprecated, but
 * might be useful when working with old versions of DataTables.
 *
 *  @name Currency
 *  @summary Sort data numerically when it has a leading currency symbol.
 *  @deprecated
 *  @author [Allan Jardine](http://sprymedia.co.uk)
 *
 *  @example
 *    $('#example').dataTable( {
 *       columnDefs: [
 *         { type: 'currency', targets: 0 }
 *       ]
 *    } );
 */

jQuery.extend( jQuery.fn.dataTableExt.oSort, {
	"currency-br-pre": function ( a ) {
		a = (a == "-") ? 0 : a.replace( /\./g, "" ).replace( /,/, "." );
		return parseFloat( a );
	},

	"currency-br-asc": function ( a, b ) {
	    var x = (a == "-") ? 0 : a.replace( /\./g, "" ).replace( /,/, "." );
	    var y = (b == "-") ? 0 : b.replace( /\./g, "" ).replace( /,/, "." );
	    x = parseFloat( x );
	    y = parseFloat( y );
	    return ((x < y) ? -1 : ((x > y) ?  1 : 0));
	},

	"currency-br-desc": function ( a, b ) {
	    var x = (a == "-") ? 0 : a.replace( /\./g, "" ).replace( /,/, "." );
	    var y = (b == "-") ? 0 : b.replace( /\./g, "" ).replace( /,/, "." );
	    x = parseFloat( x );
	    y = parseFloat( y );
	    return ((x < y) ? 1 : ((x > y) ?  -1 : 0));
	}
} );