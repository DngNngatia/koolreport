<?php
/**
 * This file contains class to export data to Microsoft Excel
 *
 * @author KoolPHP Inc (support@koolphp.net)
 * @link https://www.koolphp.net
 * @copyright KoolPHP Inc
 * @license https://www.koolreport.com/license#mit-license
 
 */

namespace koolreport\excel;
use \koolreport\core\Utility;
use \PhpOffice\PhpSpreadsheet as ps;

class ExcelExport {

    public function saveDataStoreToSheet($ds, $sheet, $option) {
        $colMetas = $ds->meta()['columns'];
        $optCols = ! empty($option['columns']) ? 
            $option['columns'] : array_keys($colMetas);
        $expColKeys = [];
        $i = 0; 
        $expColOrder = 0;
        $maxlength = array();
        foreach ($colMetas as $colKey => $colMeta) {
            $label = Utility::get($colMeta, 'label', $colKey);
            foreach ($optCols as $col)  
                if ($col === $i || $col === $colKey || $col === $label) {
                    $cell = ps\Cell\Coordinate::stringFromColumnIndex($expColOrder + 1) . 1;
                    $sheet->setCellValue($cell, $label);
                    $sheet->getStyle($cell)->getAlignment()
                        ->setHorizontal(ps\Style\Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle($cell)->getFont()->setBold(true);
                    $maxlength[] = strlen($label);

                    $expColKeys[] = $colKey;
                    $expColOrder++;
                }
            $i++;
        }

        $ds->popStart();
        $rowOrder = 0;
        while ($row = $ds->pop()) {
            foreach ($expColKeys as $expColOrder => $colKey) {
                $text = Utility::format($row[$colKey], $colMetas[$colKey]);
                $cell = ps\Cell\Coordinate::stringFromColumnIndex($expColOrder + 1) . ($rowOrder + 2);
                $sheet->setCellValue($cell, $text);
                $sheet->getStyle($cell)->getAlignment()
                    ->setHorizontal(ps\Style\Alignment::HORIZONTAL_RIGHT);
                if ($maxlength[$expColOrder] < strlen($text)) {
                    $maxlength[$expColOrder] = strlen($text);
                }
            }
            $rowOrder++;
        }
        
        for ($i = 0; $i < sizeof($maxlength); $i++) {
            $col = ps\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($col)->setWidth($maxlength[$i] + 2);
        }
    }
  
}
