<?php
namespace App\Lib;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class MyReadFilter implements IReadFilter
{
    public function readCell($columnAddress, $row, $worksheetName = '') {
        //  Read rows 1 to 7 and columns A to E only
        if (in_array($columnAddress, range('A', 'E'))) {
            return true;
        }
        return false;
    }
}