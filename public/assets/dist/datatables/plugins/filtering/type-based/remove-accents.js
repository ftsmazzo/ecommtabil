/**
 *
 *  @example
 *    $(document).ready(function() {
 *        $('#example').dataTable();
 *    } );
 *
 *  @example
 *    $(document).ready(function() {
 *        var table = $('#example').dataTable();
 *
 *        // Remove accented character from search input as well
 *        $('#myInput').keyup( function () {
 *          table
 *            .search(
 *              jQuery.fn.DataTable.ext.type.search.string( this )
 *            )
 *            .draw()
 *        } );
 *    } );
 */

(function() {
  function removeAccents(data) {
    return data.replace(/έ/g, 'ε').replace(/ύ/g, 'υ').replace(/ό/g, 'ο').replace(/ώ/g, 'ω').replace(/ά/g, 'α').replace(/ί/g, 'ι').replace(/ή/g, 'η').replace(/\n/g, ' ').replace(/[çÇ]/g, 'c').replace(/[ÃãáÁäÄàÀâÂ]/g, 'a').replace(/[ẼẽéÉèÈêÊëË]/g, 'e').replace(/[ĨĨíÍïÏîÎìÌ]/g, 'i').replace(/[ÕõóÓöÖòÒôÔ]/g, 'o').replace(/[ŨũúÚüÜùÙûÛ]/g, 'u').replace(/[Şş]/g, 's').replace(/ß/g, 'ss')
  }
  var searchType = jQuery.fn.DataTable.ext.type.search;
  searchType.string = function(data) {
    return !data ? '' : typeof data === 'string' ? removeAccents(data) : data;
  };
  searchType.html = function(data) {
    return !data ? '' : typeof data === 'string' ? removeAccents(data.replace(/<.*?>/g, '')) : data;
  };
}());