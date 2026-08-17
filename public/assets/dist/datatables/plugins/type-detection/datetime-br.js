/**
 * Automatically detect British (`dd/mm/yyyy hh:i?i:ss`) date types. Goes with the UK
 * date sorting plug-in.
 *
 *  @name Date (`dd/mm/yyyy hh:i?i:ss`)
 *  @summary Detect data which is in the date format `dd/mm/yyyy hh:i?i:ss`
 *  @author Andy McMaster
 */

jQuery.fn.dataTableExt.aTypes.unshift(
	function ( sData )
	{
		if (sData !== null && sData.match(/^(0?[1-9]|[12][0-9]|3[01])\/(0?[1-9]|1[0-2])\/(\d\d\d\d) (00|[0-9]|1[0-9]|2[0-3]):([0-9]|[0-5][0-9]):([0-9]|[0-5][0-9])$/))
		{
			return 'datetime-br';
		}
		return null;
	}
);