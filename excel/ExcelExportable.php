<?php
/**
 * This file contains class to export data to Microsoft Excel
 *
 * @author KoolPHP Inc (support@koolphp.net)
 * @link https://www.koolphp.net
 * @copyright KoolPHP Inc
 * @license https://www.koolreport.com/license#mit-license
 */

/*
    $report = new MyReport;
    $report->run()->exportToExcel(array(
        "dataStores" => array(
            'salesReport' => array(
                "columns"=>array(
                    0, 1, 2, 'column3', 'column4' //if not specifying, all columns are exported
                )
            )
        )
    ))->toBrowser("myreport.xlsx");
 */

namespace koolreport\excel;
use \koolreport\core\Utility;
use \PhpOffice\PhpSpreadsheet as ps;

trait ExcelExportable
{
    protected $excelExport;
    protected $pivotExcelExport;

    protected function getDataStoreType($dataStore) {
        $meta = $dataStore->meta()['columns'];
        $dataStore->popStart();
        $row = $dataStore->pop();
        $columns = array_keys($row);
        foreach ($columns as $c)
            if ($meta[$c]['type'] === 'dimension')
                return 'pivot';
        return 'table';
    }

    protected function getExportObject($type) {
        if ($type === 'pivot') {
            if (! isset($this->pivotExcelExport))
                $this->pivotExcelExport = new \koolreport\pivot\PivotExcelExport();
            return $this->pivotExcelExport;
        }
        else {
            if (! isset($this->excelExport))
                $this->excelExport = new ExcelExport();
            return $this->excelExport;
        }
    }

    public function exportToExcel($params=array())
    {
        $properties = Utility::get($params,"properties",array());
        
        $spreadsheet = new ps\Spreadsheet();
        $spreadsheet->getProperties()
        ->setCreator(Utility::get($properties,"creator","KoolReport"))
        ->setTitle(Utility::get($properties,"title",""))
        ->setDescription(Utility::get($properties,"description",""))
        ->setSubject(Utility::get($properties,"subject",""))
        ->setKeywords(Utility::get($properties,"keywords",""))
        ->setCategory(Utility::get($properties,"category",""));
        
        $options = array();
        $dataStoreNames = Utility::get($params,"dataStores",null);
        if (! isset($dataStoreNames) || ! is_array($dataStoreNames))
            $exportDataStores = $this->dataStores;
        else {
            $options = array();
            $exportDataStores = array();
            foreach ($dataStoreNames as $k => $v)
                if (isset($this->dataStores[$k])) {
                    $exportDataStores[$k] = $this->dataStores[$k];
                    $options[$k] = $v;
                }
                else if (isset($this->dataStores[$v]))
                    $exportDataStores[$v] = $this->dataStores[$v];
        }

        $k=0;
        foreach($exportDataStores as $name=>$dataStore) {
            if ($k==0) {
                $sheet = $spreadsheet->getSheet(0);
            }
            else {
                $sheet = new ps\Worksheet\Worksheet($spreadsheet, $name);
                $spreadsheet->addSheet($sheet, $k);
            }
            $sheet->setTitle($name);
            $option = Utility::get($options,$name,array());
            $type = $this->getDataStoreType($dataStore);
            $exportObject = $this->getExportObject($type);
            $exportObject->saveDataStoreToSheet($dataStore, $sheet, $option);
            $k++;
        }

        $tmpFilePath = sys_get_temp_dir()."/".Utility::getUniqueId().".xlsx";
        $objWriter = ps\IOFactory::createWriter($spreadsheet, "Xlsx");
        $objWriter->save($tmpFilePath);

        return new FileHandler($tmpFilePath);
    }
}
