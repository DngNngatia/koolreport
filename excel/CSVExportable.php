<?php
/**
 * This file contains class to export data to Microsoft Excel
 *
 * @author KoolPHP Inc (support@koolphp.net)
 * @link https://www.koolphp.net
 * @copyright KoolPHP Inc
 * @license https://www.koolreport.com/license#mit-license
 * 
 * 
 */

 /*
    $report = new MyReport;
    $report->run()->exportToCSV(array(
        "dataStores" => array(
            'salesReport' => array(
                'delimiter' => ';',
                "columns"=>array(
                    0, 1, 2, 'column3', 'column4', //if not specifying, all columns are exported
                )
            )
        )
    ))->toBrowser("myreport.csv");
 * 
 */


namespace koolreport\excel;
use \koolreport\core\Utility;

trait CSVExportable
{
    public function exportToCSV($params = array()) {
        $content = "";
        $options = array();
        $dataStoreNames = Utility::get($params,"dataStores",null);
        if (is_string($dataStoreNames))
            $dataStoreNames = array_map('trim', explode(',', $dataStoreNames));
        if (! is_array($dataStoreNames))
            $exportDataStores = $this->dataStores;
        else {
            $options = array();
            $exportDataStores = array();
            foreach ($dataStoreNames as $k => $v)
                if (isset($this->dataStores[$k])) {
                    $exportDataStores[$k] = $this->dataStores[$k];
                    $options[$k] = $v;
                }
                else if (is_string($v) && isset($this->dataStores[$v]))
                    $exportDataStores[$v] = $this->dataStores[$v];
        }

        foreach($exportDataStores as $name=>$ds) {
            $option = Utility::get($options, $name, []);
            $colMetas = $ds->meta()['columns'];
            $optCols = Utility::get($option, 'columns', array_keys($colMetas));
            $expColKeys = [];
            $expColLabels = [];
            $i = 0;
            foreach ($colMetas as $colKey => $colMeta) {
                $label = Utility::get($colMeta, 'label', $colKey);
                foreach ($optCols as $col)
                    if ($col === $i || $col === $colKey || $col === $label) {
                        $expColKeys[] = $colKey;
                        $expColLabels[] = $label;
                    }
                $i++;
            }

            $delimiter = Utility::get($option, 'delimiter', ',');
            $content .= implode($delimiter, $expColLabels) . "\n";

            $ds->popStart();
            while ($row = $ds->pop()) {
                foreach ($expColKeys as $colKey) {
                    $content .= Utility::format($row[$colKey], $colMetas[$colKey])
                        . $delimiter;
                }
                $content = substr($content, 0, -1) . "\n";
            }
        }

        $tmpFilePath = sys_get_temp_dir()."/".Utility::getUniqueId().".csv";
        $file = fopen($tmpFilePath, 'w') or die('Cannot open file:  '.$tmpFilePath);
        fwrite($file, $content);
        fclose($file);

        return new FileHandler($tmpFilePath);
    }
}
