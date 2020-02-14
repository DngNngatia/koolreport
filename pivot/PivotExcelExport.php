<?php
/**
 * This file contains class to export data to Microsoft Excel
 *
 * @author KoolPHP Inc (support@koolphp.net)
 * @link https://www.koolphp.net
 * @copyright KoolPHP Inc
 * @license https://www.koolreport.com/license#mit-license

    $report = new myReport();
    $report->run()
    ->exportToExcel(array(
        "dataStores" => array(
            'sales' => array(
                // 'rowDimension' => 'column',
                // 'columnDimension' => 'row',
                "measures" => array(
                    "dollar_sales - sum",
                    // 'dollar_sales - count',
                ),
                'rowSort' => array(
                    // 'orderMonth' => function($a, $b) {
                    // return (int)$a > (int)$b;
                    // },
                    // 'orderDay' => function($a, $b) {
                    // return (int)$a > (int)$b;
                    // },
                    'dollar_sales - sum' => 'desc',
                ),
                'columnSort' => array(
                    'orderMonth' => function ($a, $b) {
                        return (int) $a < (int) $b;
                    },
                    // 'dollar_sales - sum' => 'desc',
                    // 'orderYear' => 'desc',
                ),
                // 'headerMap' => array(
                // 'dollar_sales - sum' => 'Sales (in USD)',
                // 'dollar_sales - count' => 'Number of Sales',
                // ),
                'headerMap' => function ($v, $f) {
                    if ($v === 'dollar_sales - sum') {
                        $v = 'Sales (in USD)';
                    }

                    if ($v === 'dollar_sales - count') {
                        $v = 'Number of Sales';
                    }

                    if ($f === 'orderYear') {
                        $v = 'Year ' . $v;
                    }

                    return $v;
                },
                // 'dataMap' => function($v, $f) {return $v;},
            ),
        ),
    ))
    ->toBrowser("myReport.xlsx");

 */

namespace koolreport\pivot;

use \koolreport\core\Utility;
use \PhpOffice\PhpSpreadsheet as ps;

class PivotExcelExport
{

    public function saveDataStoreToSheet($dataStore, $sheet, $option)
    {
        $totalName = Utility::get($option, 'totalName', 'Total');
        $emptyValue = Utility::get($option, 'emptyValue', '-');
        $headerMap = Utility::get($option, 'headerMap', array());
        $dataMap = Utility::get($option, 'dataMap', array());
        $meta = $dataStore->meta()['columns'];

        $pivotUtil = new PivotUtil($dataStore, $option);
        $fni = $pivotUtil->getFieldsNodesIndexes();
        $rowNodes = $fni['mappedRowNodes'];
        $rowNodesMark = $fni['rowNodesMark'];
        $rowIndexes = $fni['rowIndexes'];
        $rowFields = array_values($fni['rowFields']);
        $colNodes = $fni['mappedColNodes'];
        $colNodesMark = $fni['colNodesMark'];
        $colIndexes = $fni['colIndexes'];
        $colFields = array_values($fni['colFields']);
        $dataNodes = $fni['dataNodes'];
        $dataFields = array_values($fni['dataFields']);
        $indexToData = $fni['indexToData'];

        $cell = ps\Cell\Coordinate::stringFromColumnIndex(1) . 1;
        $endCell = ps\Cell\Coordinate::stringFromColumnIndex(
            count($rowFields)) . count($colFields);
        $sheet->setCellValue($cell, implode(' | ', $dataNodes));
        $sheet->mergeCells($cell . ":" . $endCell);
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(
            ps\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($cell)->getAlignment()->setVertical(
            ps\Style\Alignment::VERTICAL_TOP);

        foreach ($colFields as $i => $f) {
            foreach ($colIndexes as $c => $j) {
                $node = $colNodes[$j];
                $nodeMark = $colNodesMark[$j];
                if (isset($nodeMark[$f . '_colspan'])) {
                    $rowspan = $nodeMark[$f . '_rowspan'] - 1;
                    $colspan = $nodeMark[$f . '_colspan'] - 1;
                    $cell = ps\Cell\Coordinate::stringFromColumnIndex(
                        count($rowFields) + $c * count($dataFields) + 1)
                        . ($i + 1);
                    $endCell = ps\Cell\Coordinate::stringFromColumnIndex(
                        count($rowFields) + $c * count($dataFields) + $colspan + 1)
                        . ($i + 1 + $rowspan);
                    $sheet->mergeCells($cell . ":" . $endCell);
                    $sheet->getCell($cell)->setValue($node[$f]);
                    $sheet->getStyle($cell)->getAlignment()->setHorizontal(
                        ps\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle($cell)->getAlignment()->setVertical(
                        ps\Style\Alignment::VERTICAL_TOP);
                }
            }
        }

        $maxLength = array_fill(0, count($rowFields), 0);
        foreach ($rowIndexes as $r => $i) {
            $node = $rowNodes[$i];
            $nodeMark = $rowNodesMark[$i];
            foreach ($rowFields as $j => $rf) {
                if (isset($nodeMark[$rf . '_rowspan'])) {
                    $rowspan = $nodeMark[$rf . '_rowspan'] - 1;
                    $colspan = $nodeMark[$rf . '_colspan'] - 1;
                    $cell = ps\Cell\Coordinate::stringFromColumnIndex(
                        $j + 1)
                        . (count($colFields) + $r + 1);
                    $endCell = ps\Cell\Coordinate::stringFromColumnIndex(
                        $j + $colspan + 1)
                        . (count($colFields) + $r + 1 + $rowspan);

                    $sheet->mergeCells($cell . ":" . $endCell);
                    $text = $node[$rf];
                    $sheet->getCell($cell)->setValue($text);
                    $sheet->getStyle($cell)->getAlignment()->setVertical(
                        ps\Style\Alignment::VERTICAL_CENTER);
                    if ($maxLength[$j] < strlen($text)) {
                        $maxLength[$j] = strlen($text);
                    }

                }
            }

            foreach ($colIndexes as $c => $j) {
                $dataRow = isset($indexToData[$i][$j]) ?
                $indexToData[$i][$j] : array();
                foreach ($dataFields as $k => $df) {
                    $cell = ps\Cell\Coordinate::stringFromColumnIndex(
                        count($rowFields) + $c * count($dataFields) + $k + 1)
                        . (count($colFields) + $r + 1);
                    $sheet->getCell($cell)->setValue(isset($dataRow[$df]) ?
                        $dataRow[$df] : $emptyValue);
                    $sheet->getStyle($cell)->getAlignment()->setHorizontal(
                        ps\Style\Alignment::HORIZONTAL_RIGHT);
                }
            }
        }

        for ($i = 0; $i < sizeof($maxLength); $i++) {
            $col = ps\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($col)->setWidth($maxLength[$i]);
        }
    }

}
