<?php
/* Usage
    PivotMatrix::create(array(
        "name" => "pivotMatrix1",
        'template' => 'PivotMatrix-Bun',
        'dataSource' => $this->dataStore('sales'),
        // "measures"=>array(
        //     "dollar_sales - sum", 
        // ),
        'rowSort' => array(
            'dollar_sales - sum' => 'desc',
            'productLine' => 'desc',
        ),
        'columnSort' => array(
            'orderMonth' => function($a, $b) {
                return (int)$a < (int)$b;
            },
        ),
        // 'columnExpandLevel' => 0,
        // 'rowExpandLevel' => 0,
        'width' => '100%',
        'height' => '800px',
        'headerMap' => function($v, $f) {
            switch ($v) {
                case 'dollar_sales - sum': return 'Total Sales';
                case 'dollar_sales - sum percent': return 'Sales Percentage';
                case 'dollar_sales - count': return 'Number of Sales';
                case 'dollar_sales - avg': return 'Average Sales';
                case 'orderYear': return 'Year';
                case 'orderMonth': return 'Month';
                case 'orderDay': return 'Day';
                case 'customerName': return 'Customer';
                case 'productLine': return 'Category';
                case 'productName': return 'Product';
            }
            $r = $v;
            if ($f === 'orderYear')
                $r = 'Year ' . $v;
            $map = array(
                '1' => 'January',
                '2' => 'February',
                '3' => 'March',
                '4' => 'April',
                '5' => 'May',
                '6' => 'June',
                '7' => 'July',
                '8' => 'August',
                '9' => 'September',
                '10' => 'October',
                '11' => 'November',
                '12' => 'December',
            );
            // if ($f === 'orderMonth')
            //     $r = "<a target='_blank' href='../../file_$v.pdf'>" . $map[$v] . "</a>";
            return $r;
        },
        'totalName' => 'All',
        'waitingFields' => array(
            'dollar_sales - count' => 'data', 
            'orderMonth' => 'label',
            'orderDay' => 'label',
            'productLine' => 'label',
            'productName' => 'label',
        ),
        'paging' => array(
            // 'size' => 925,
            // 'maxDisplayedPages' => 5,
            // 'sizeSelect' => array(5, 10, 20, 50, 100)
        ),
        'map' => array(
            // 'rowField' => function($rowField, $fieldInfo) {
            //     return $rowField;
            // },
            // 'columnField' => function($colField, $fieldInfo) {
            //     return $colField;
            // },
            'dataField' => function($dataField, $fieldInfo) {
                // Util::prettyPrint($fieldInfo);
                $v = $dataField;
                if ($v === 'dollar_sales - sum')
                    $v = 'Sales (in USD)';
                else if ($v === 'dollar_sales - count')
                    $v = 'Number of Sales';
                return $v;
            },
            // 'waitingField' => function($waitingField, $fieldInfo) {
            //     return $waitingField;
            // },
            'rowHeader' => function($rowHeader, $headerInfo) {
                // Util::prettyPrint($headerInfo);
                $v = $rowHeader;
                if (isset($headerInfo['childOrder']))
                    $v = $headerInfo['childOrder'] . ". " . $v;
                return $v;
            },
            'columnHeader' => function($colHeader, $headerInfo) {
                $v = $colHeader;
                if ($headerInfo['fieldName'] === 'orderYear')
                    $v = 'Year-' . $v;
                else if ($headerInfo['fieldName'] === 'orderQuarter')
                    $v = 'Quarter-' . $v;

                if (isset($headerInfo['childOrder']))
                    $v = $headerInfo['childOrder'] . ". " . $v;
                return $v;
            },
            'dataCell' => function($value, $cellInfo) {
                // Util::prettyPrint($cellInfo);
                $rfOrder = $cellInfo['row']['fieldOrder'];
                $cfOrder = $cellInfo['column']['fieldOrder'];
                $df = $cellInfo['fieldName'];
                $dfOrder = $cellInfo['fieldOrder'];
                // return "$rfOrder:$cfOrder:$df. $value";
                
                return $cellInfo['formattedValue'];
            },
        ),
        'cssClass' => array(
            'waitingField' => function($field) {
                return 'wf-' . $field;
            },
            'dataField' => function($field) {
                return 'df-' . $field;
            },
            'columnField' => function($field) {
                return 'cf-' . $field;
            },
            'rowField' => function($field) {
                return 'rf-' . $field;
            },
            'columnHeader' => function($field, $header) {
                return 'ch-' . $header;
            },
            'rowHeader' => function($field, $header) {
                return 'rh-' . $header;
            },
            'dataCell' => function($dataField, $value) {
                return 'dc-' . $value;
            },
        ),
        'clientEvents' => array(
            // 'afterFieldMove' => 'handleAfterFieldMove'
        ),
        // 'hideSubtotalRow' => true,
        'hideSubtotalColumn' => true,
        'showDataHeaders' => true,
    ));
 **/

namespace koolreport\pivot\widgets;
use \koolreport\pivot\PivotUtil;
use \koolreport\core\Widget;
use \koolreport\core\Utility;

class PivotMatrix extends Widget
{
    private static $instanceId = 0;
    protected $name;
    protected $width;
    protected $height;
    protected $emptyValue;
    protected $totalName;
    protected $rowCollapseLevels;
    protected $colCollapseLevels;
    protected $waitingFields;
    protected $scope;

    public function version()
	{
		return "5.0.0";
	}

    protected function resourceSettings()
    {
        return array(
            "library"=>array("font-awesome"),
            "folder"=>"assets",
            "js"=>array(
                "PivotMatrix.js",
            ),
            "css"=>array(
                "PivotMatrix.css",
                "animate.min.css",
            )
        );        
    }
	
	protected function onInit()
	{
        $this->name = Utility::get($this->params, 'id', null);
        $this->name = Utility::get($this->params, 'name', $this->name);
        $this->useAutoName("pivotMatrix_");
        $this->pivotMatrixId = "krpm_" . $this->name;
        $this->useDataSource();

		$this->template = Utility::get($this->params, 'template', 'PivotMatrix');
		$this->width = Utility::get($this->params, 'width', 'auto');
		$this->height = Utility::get($this->params, 'height', 'auto');
		$this->emptyValue = Utility::get($this->params, 'emptyValue', '-');
		$this->totalName = Utility::get($this->params,'totalName','Total');
		$this->rowCollapseLevels = Utility::get($this->params,'rowCollapseLevels',array());
		$this->colCollapseLevels = Utility::get($this->params,'columnCollapseLevels',array());
		$this->clientEvents = Utility::get($this->params,'clientEvents',array());
		$this->rowSort = Utility::get($this->params,'rowSort',array());
		$this->columnSort = Utility::get($this->params,'columnSort',array());
		$this->scope = Utility::get($this->params,'scope',array());
		$this->columnWidth = Utility::get($this->params,'columnWidth','90px');
        $this->cssClass = Utility::get($this->params,'cssClass',array());
        $this->hideSubtotalRow = Utility::get($this->params, 'hideSubtotalRow', false);
        $this->hideSubtotalColumn = Utility::get($this->params, 'hideSubtotalColumn', false);
        $this->showDataHeaders = Utility::get($this->params, 'showDataHeaders', false);
	}
  
	protected function onRender()
	{
		if (! $this->dataStore) return array();
        $dataStore = $this->dataStore;
        $meta = $dataStore->meta()['columns'];

        $paging = null;
        if (isset($this->params['paging'])) {
            $paging = $this->params['paging'];
            if (! is_array($paging)) $paging = array();
            $paging = array_merge(array(
                'page' => 1,
                'size' => 10,
                'maxDisplayedPages' => 5,
                'sizeSelect' => array(5, 10, 20, 50, 100)
            ), $paging);
        }
        $page = Utility::get($paging, 'page', null);
        $pageSize = Utility::get($paging, 'size', null);

        $scrollTopPercentage = $scrollLeftPercentage = 0;

        $isUpdate = false;
        if (isset($_POST['koolPivotConfig'])) {
            $config = json_decode($_POST['koolPivotConfig'], true);  
            if ($config['pivotMatrixId'] == $this->pivotMatrixId)
                $isUpdate = true;
        }

        if ($isUpdate) {
            // print_r($_POST['koolPivotConfig']); echo '<br><br>';
            // print_r($_POST['koolPivotViewstate']); echo '<br><br>';
            $this->params['measures'] = $config['dataFields'];
            $waitingFields = $config['waitingFields'];
            $waitingFieldsType = $config['waitingFieldsType'];
            $fs = array();
            foreach ($waitingFields as $i => $field)
                $fs[$field] = $waitingFieldsType[$i];
            $this->params['waitingFields'] = $fs;
            $viewstate = json_decode($_POST['koolPivotViewstate'], true);
            $paging = $viewstate["paging"];
            $scrollTopPercentage = $viewstate["scrollTopPercentage"];
            $scrollLeftPercentage = $viewstate["scrollLeftPercentage"];

            $this->rowSort = $this->params['rowSort'] = $config['rowSort'];
            $this->columnSort = $this->params['columnSort'] = $config['columnSort'];
            $this->columnWidth = $config['columnWidth'];
        }
        
        $pivotUtil = new PivotUtil($this->dataStore, $this->params);
        $FieldsNodesIndexes = $pivotUtil->getFieldsNodesIndexes();

        $expandTree = $this->dataStore->meta()['expandTrees'];
        // Utility::prettyPrint($expandTree);

        // Utility::prettyPrint($meta);
        // Utility::prettyPrint($dataStore->data());
        // Utility::prettyPrint($FieldsNodesIndexes);
        echo "<pivotmatrix id='$this->pivotMatrixId'>";
        $this->template($this->template, array_merge(
            array(
                'uniqueId' => $this->name,
                'width' => $this->width,
                'height' => $this->height,
                'totalName' => $this->totalName,
                'emptyValue' => $this->emptyValue,
                'rowCollapseLevels' => $this->rowCollapseLevels,
                'colCollapseLevels' => $this->colCollapseLevels,
                'clientEvents' => $this->clientEvents,
                'cssClass' => $this->cssClass,
                'hideSubtotalRow' => $this->hideSubtotalRow,
                'hideSubtotalColumn' => $this->hideSubtotalColumn,
                'config' => array(
                    'pivotId' => $this->dataStore->meta()['pivotId'],
                    'expandTrees' => $expandTree,
                    'pivotMatrixId' => $this->pivotMatrixId,
                    'waitingFields' => $FieldsNodesIndexes['waitingFields'],
                    'dataFields' => $FieldsNodesIndexes['dataFields'],
                    'columnFields' => $FieldsNodesIndexes['colFields'],
                    'rowFields' => $FieldsNodesIndexes['rowFields'],
                    'waitingFieldsType' => $FieldsNodesIndexes['waitingFieldsType'],
                    'dataFieldsType' => $FieldsNodesIndexes['dataFieldsType'],
                    'columnFieldsType' => $FieldsNodesIndexes['columnFieldsType'],
                    'rowFieldsType' => $FieldsNodesIndexes['rowFieldsType'],
                    'waitingFieldsSort' => $FieldsNodesIndexes['waitingFieldsSort'],
                    'dataFieldsSort' => $FieldsNodesIndexes['dataFieldsSort'],
                    'columnFieldsSort' => $FieldsNodesIndexes['columnFieldsSort'],
                    'rowFieldsSort' => $FieldsNodesIndexes['rowFieldsSort'],
                    'rowSort' => $this->rowSort,
                    'columnSort' => $this->columnSort,
                    'columnWidth' => $this->columnWidth,
                ),
                'viewstate' => array(
                    'paging' => $paging,
                    'scrollTopPercentage' => $scrollTopPercentage,
                    'scrollLeftPercentage' => $scrollLeftPercentage,
                ),
                'scope' => $this->scope
            ),
            $FieldsNodesIndexes
        ));
        echo "</pivotmatrix>";
        if ($isUpdate && isset($_POST['partialRender'])) exit;
	}	
}