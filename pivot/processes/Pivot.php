<?php
/**
 * This file contains process to turn data into pivot table
 *
 * @author KoolPHP Inc (support@koolphp.net)
 * @link https://www.koolphp.net
 * @copyright KoolPHP Inc
 * @license https://www.koolreport.com/license#regular-license
 * @license https://www.koolreport.com/license#extended-license
 */
/* 
    ->pipe(new Pivot(array(
        "dimensions"=>array(
            "column"=>"orderYear, orderMonth",
            "row"=>"customerName, productLine, productName"
        ),
        "aggregates"=>array(
            "sum"=>"dollar_sales",
            "count"=>"dollar_sales",
            "avg"=>"dollar_sales",
            'sum percent' => 'dollar_sales',
            'count percent' => 'dollar_sales',
        )
    )))
  */

namespace koolreport\pivot\processes;

use \koolreport\core\Utility as Util;

class Pivot extends \koolreport\core\Process
{
    private static $instanceId = 0;

    protected $pivotId;
    protected $dimensions = array();
    protected $aggregates = array();
    protected $data = array();
    protected $count = array();
    protected $countAll = array();
    protected $sumAll = array();
    protected $hasAvg = array();
    protected $hasCountPercent = array();
    protected $hasSumPercent = array();

    protected $nameToIndexD = array();
    protected $indexToNameD = array();
    protected $nameToNode = [];
    protected $forwardMeta;

    protected $partialProcessing;
    protected $expandTrees;
    protected $command;

    public function onInit()
    {
        $this->pivotId = $this::$instanceId++;
        $this->partialProcessing = Util::get($this->params, "partialProcessing", false);
        $trimArray = function ($arr, $defaultArr = []) {
            if (empty($arr)) {
                $arr = [];
            }

            $arr = is_string($arr) ? explode(",", $arr) : $arr;
            $arr = array_map('trim', $arr);
            $arr = array_filter($arr, function ($v) {return !empty($v);});
            return !empty($arr) ? $arr : $defaultArr;
        };
        $isUpdate = false;
        if (isset($_POST['koolPivotConfig'])) {
            $config = json_decode($_POST['koolPivotConfig'], true);
            if ($config['pivotId'] == $this->pivotId) {
                $isUpdate = true;
            }

        }
        if ($isUpdate) {
            $this->expandTrees = $config['expandTrees'];
            $this->command = json_decode($_POST['koolPivotCommand'], true);
            $columnFields = $config["columnFields"];
            $rowFields = $config["rowFields"];
            if ($columnFields[0] === 'root') {
                $columnFields = array_slice($columnFields, 1);
            }

            if ($rowFields[0] === 'root') {
                $rowFields = array_slice($rowFields, 1);
            }

            $dimensions = array(
                'column' => $columnFields,
                'row' => $rowFields,
            );
            $this->dimensions = array();
            foreach ($dimensions as $d => $fields) {
                $this->dimensions[$d] = array();
                if (is_string($fields)) {
                    $fields = explode(',', $fields);
                }

                foreach ($fields as $field) {
                    $field = trim($field);
                    if (!empty($field)) {
                        array_push($this->dimensions[$d], $field);
                    }

                }
            }
            $aggregates = array();
            $measures = $config["dataFields"];
            // $measures = [
            //     "dollar_sales - sum",
            //     "order_id - count",
            //     "dollar_sales - avg",
            // ];
            foreach ($measures as $measure) {
                $fieldAgg = explode(" - ", $measure);
                if (empty($aggregates[$fieldAgg[1]])) {
                    $aggregates[$fieldAgg[1]] = $fieldAgg[0];
                } else {
                    $aggregates[$fieldAgg[1]] .= ", " . $fieldAgg[0];
                }

            }

        } else {
            $this->dimensions = Util::get($this->params, "dimensions", array());
            $this->expandTrees = array();
            $this->command = array();
            foreach ($this->dimensions as $d => $fields) {
                $this->expandTrees[$d] = array(
                    'name' => 'root',
                    'children' => array(),
                );
                $this->command[$d] = array();
            }
            $dimensions = array();
            foreach ($this->dimensions as $d => $fields) {
                $dimensions[$d] = $trimArray($fields);
            }
            $this->dimensions = $dimensions;
            $aggregates = Util::get($this->params, "aggregates", array());
        }

        $this->hasAvg = false;
        // $aggregates = [
        //     "sum"=>"dollar_sales",
        //     "count"=>"dollar_sales, order_id",
        //     "avg"=>"dollar_sales",
        // ];
        foreach ($aggregates as $operator => $aggFields) {
            $op = trim($operator);
            $aggFields = $trimArray($aggFields);
            foreach ($aggFields as $af) {
                Util::init($this->aggregates, $af, []);
                $this->aggregates[$af][] = $op;
                if ($op === 'avg') {
                    $this->hasAvg[$af] = true;
                }
                if ($op === 'count percent') {
                    $this->hasCountPercent[$af] = true;
                }
                if ($op === 'sum percent') {
                    $this->hasSumPercent[$af] = true;
                }
            }
        }
        // $this->aggregates = [
        //     'dollar_sales' => ['sum', 'count', 'avg'],
        //     'order_id' => ['count']
        // ];

        foreach ($this->dimensions as $d => $dimension) {
            $this->nameToIndexD[$d] = array();
            $this->indexToNameD[$d] = array();
        }

    }

    protected function addToTree(&$expandTree, $fields, $node, $level)
    {
        $tree = &$expandTree;
        for ($i = 0; $i < $level && $i < count($fields); $i++) {
            $field = $fields[$i];
            if ($node[$field] === '{{all}}') {
                continue;
            }

            $foundNode = false;
            foreach ($tree['children'] as &$child) {
                if ((string)$child['name'] === (string)$node[$field]) {
                    $tree = &$child;
                    $foundNode = true;
                    break;
                }
            }

            if (!$foundNode) {
                $newNode = array(
                    'name' => $node[$field],
                    'children' => array(),
                );
                array_push($tree['children'], $newNode);
                $tree = &$newNode;
            }
        }
        return true;
    }

    protected function isInTree(&$expandTree, $fields, $node)
    {
        $tree = &$expandTree;
        foreach ($fields as $i => $field) {
            if ($i === 0) {
                continue;
            }

            if ($node[$field] === '{{all}}') {
                break;
            }

            $parentField = $fields[$i - 1];
            $foundParentNode = false;
            foreach ($tree['children'] as &$child) {
                if ((string)$child['name'] === (string)$node[$parentField]) {
                    $tree = &$child;
                    $foundParentNode = true;
                    break;
                }
            }

            if (!$foundParentNode) {
                return false;
            }

        }
        return true;
    }

    public function onInput($row)
    {
        // $this->index++;
        // if ($this->index % 10000 === 0)
        //     echo $this->index . '<br>';
        $nodesD = array();

        foreach ($this->dimensions as $dimensionName => $labelFields) {
            $d = $dimensionName;
            $expandTree = &$this->expandTrees[$d];
            $command = &$this->command[$d];
            $nameToIndex = &$this->nameToIndexD[$d];
            $indexToName = &$this->indexToNameD[$d];
            $nodesD[$d] = array();

            $node = array();
            foreach ($labelFields as $i => $labelField) {
                $node[$labelField] = '{{all}}';
            }

            $nodeName = implode(' - ', $node);
            if (!isset($nameToIndex[$nodeName])) {
                $index = count($indexToName);
                $nameToIndex[$nodeName] = $index;
                $indexToName[$index] = $node;
            }
            array_push($nodesD[$d], 0);
            foreach ($labelFields as $i => $labelField) {
                $node[$labelField] = Util::get($row, $labelField, '{{other}}');
                $expandLevel = Util::get($command, "expand", -1);
                if (!$this->partialProcessing) {
                    $expandLevel = count($labelFields);
                }

                if ($expandLevel >= $i) {
                    $this->addToTree($expandTree, $labelFields, $node, $expandLevel);
                } else if (!$this->isInTree($expandTree, $labelFields, $node)) {
                    continue;
                }

                $nodeName = implode(' - ', $node);
                if (!isset($nameToIndex[$nodeName])) {
                    $index = count($indexToName);
                    $nameToIndex[$nodeName] = $index;
                    $indexToName[$index] = $node;
                }
                array_push($nodesD[$d], $nameToIndex[$nodeName]);
            }
        }

        $data = &$this->data;
        $count = &$this->count;
        $countAll = &$this->countAll;
        $sumAll = &$this->sumAll;
        $dataNodes = $this->buildDataNodes($nodesD);
        foreach ($this->aggregates as $aggregateField => $operators) {
            $af = $aggregateField;
            if (!isset($row[$af])) {
                continue;
            }

            foreach ($dataNodes as $dataNode) {
                $dn = $datanodeName = implode(" : ", $dataNode);
                $this->nameToNode[$dn] = $dataNode;

                Util::init($data, $dn, []);
                Util::init($data[$dn], $af, []);
                Util::init($count, $dn, []);
                $datum = &$data[$dn][$af];
                foreach ($operators as $op) {
                    Util::init($datum, $op, $this->initValue($op));
                    $datum[$op] = $this->aggValue($op, $datum[$op], $row[$af]);
                }
                unset($datum);
                if (isset($this->hasAvg[$af])) {
                    Util::init($count[$dn], $af, $this->initValue('count'));
                    $count[$dn][$af] += 1;
                }
            }

            if (isset($this->hasCountPercent[$af])) {
                Util::init($countAll, $af, $this->initValue('count'));
                $countAll[$af] += 1;
            }
            if (isset($this->hasSumPercent[$af])) {
                Util::init($sumAll, $af, $this->initValue('sum'));
                $sumAll[$af] += $row[$af];
            }
        }
    }

    private function buildDataNodes($nodesD)
    {
        $dataNodes = array();
        $nodes1 = reset($nodesD);
        if (count($nodesD) <= 1) {
            foreach ($nodes1 as $node1) {
                $dataNode = [$node1];
                $dataNodes[] = $dataNode;
            }
            return $dataNodes;
        }
        $nodesD2 = array_slice($nodesD, 1);
        $dataNodes2 = $this->buildDataNodes($nodesD2);
        foreach ($nodes1 as $node1) {
            $dataNode1 = [$node1];
            foreach ($dataNodes2 as $dataNode2) {
                $dataNode = array_merge($dataNode1, $dataNode2);
                $dataNodes[] = $dataNode;
            }
        }
        return $dataNodes;
    }

    private function initValue($operator)
    {
        switch ($operator) {
            case 'min':
                return PHP_INT_MAX;
            case 'max':
                return PHP_INT_MIN;
            case 'sum':
            case 'count':
            case 'avg':
            default:
                return 0;
        }
    }

    private function aggValue($operator, $value1, $value2 = null)
    {
        switch ($operator) {
            case 'min':
                return min($value1, $value2);
            case 'max':
                return max($value1, $value2);
            case 'count':
            case 'count percent':
                return $value1 + 1;
            case 'avg':
            case 'sum':
            case 'sum percent':
            default:
                return (float) $value1 + (float) $value2;
        }
    }

    public function finalize()
    {
        $metaData = array();
        foreach ($this->dimensions as $dimensionName => $fields) {
            $d = $dimensionName;
            $metaDimension = array();
            $indexToName = $this->indexToNameD[$d];
            foreach ($indexToName as $i => $name) {
                array_push($metaDimension, $name);
            }
            $metaData[$d] = array('type' => 'dimension', 'index' => $metaDimension);
        }
        $this->forwardMeta['columns'] = array_merge(
            $this->forwardMeta['columns'], $metaData);

        // if aggregate operator is average, divide total sum by total count
        foreach ($this->data as $dn => &$nodeValues) {
            foreach ($nodeValues as $af => &$datum) {
                if (isset($this->hasAvg[$af])) {
                    $datum['avg'] *= 1 / $this->count[$dn][$af];
                }
                if (isset($this->hasCountPercent[$af])) {
                    $datum['count percent'] *= 100 / $this->countAll[$af];
                }
                if (isset($this->hasSumPercent[$af])) {
                    $datum['sum percent'] *= 100 / $this->sumAll[$af];
                }
            }
        }
        unset($nodeValues, $datum);

        $rows = array();
        $dimensionNames = array_keys($this->dimensions);
        foreach ($this->data as $dName => $nodeValues) {
            $row = array();
            foreach ($nodeValues as $af => $datum) {
                foreach ($datum as $operator => $value) {
                    $row[$af . ' - ' . $operator] = $value;
                }
            }

            $dataNode = $this->nameToNode[$dName];
            foreach ($dataNode as $i => $dataNode) {
                $row[$dimensionNames[$i]] = $dataNode;
            }
            array_push($rows, $row);
        }
        $this->data = &$rows;
    }

    public function receiveMeta($metaData, $source)
    {
        $this->metaData = array_merge($this->metaData, $metaData);
        $cMetas = $this->metaData['columns'];
        foreach ($this->aggregates as $af => $operators) {
            foreach ($cMetas as $cName => $cMeta) {
                if ($cName !== $af) {
                    continue;
                }

                foreach ($operators as $op) {
                    $aggField = $af . ' - ' . $op;
                    if ($op === 'count') {
                        $cMetas[$aggField] = ['type' => 'number'];
                    } else if ($op === 'count percent' || $op === 'sum percent') {
                        $cMetas[$aggField] = [
                            'type' => 'number',
                            'decimals' => 2,
                            'suffix' => '%',
                        ];
                    } else {
                        $cMetas[$aggField] = $cMeta;
                    }

                }
            }
        }
        $this->forwardMeta = [
            'pivotId' => $this->pivotId,
            'expandTrees' => &$this->expandTrees,
            'columns' => $cMetas,
        ];
    }

    public function onInputEnd()
    {
        $this->finalize();

        $this->sendMeta($this->forwardMeta);

        foreach ($this->data as $row) {
            $this->next($row);
        }
    }
}
