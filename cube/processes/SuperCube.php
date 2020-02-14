<?php
/**
 * This file contains process to turn data into cross-tab table
 *
 * @author KoolPHP Inc (support@koolphp.net)
 * @link https://www.koolphp.net
 * @copyright KoolPHP Inc
 * @license https://www.koolreport.com/license#regular-license
 * @license https://www.koolreport.com/license#extended-license
 */
/* 
    ->pipe(new SuperCube(array(
        // "row" => "customerName",
        "rows" => "customerName, customerId",
        // "column" => "orderQuarter",
        "columns" => "orderQuarter, orderYear",
        "sum" => "dollar_sales, tax_amount",
        "count" => "order_id",
    )));
 */
namespace koolreport\cube\processes;

use \koolreport\core\Utility as Util;

class SuperCube extends \koolreport\core\Process
{
    protected $rows;
    protected $columns;
    protected $row;
    protected $column;
    protected $aggregate;
    protected $aggregates = array();
    protected $operators = array('sum', 'count', 'avg', 'min', 'max',
        'count percent', 'sum percent');
    protected $hasAvg = array();
    protected $hasCountPercent = array();
    protected $hasSumPercent = array();
    protected $data = array();
    protected $count = array();
    protected $countAll = array();
    protected $sumAll = array();
    protected $emptyValue = 0;

    protected $nodeToIndex = array();
    protected $indexToNode = array();
    protected $forwardMeta;

    public function onInit()
    {
        $params = $this->params;
        $trimArray = function ($arr, $defaultArr = []) {
            if (empty($arr)) {
                $arr = [];
            }

            $arr = is_string($arr) ? explode(",", $arr) : $arr;
            $arr = array_map('trim', $arr);
            $arr = array_filter($arr, function ($v) {return !empty($v);});
            return !empty($arr) ? $arr : $defaultArr;
        };

        $row = Util::get($params, "row", '');
        $rows = Util::get($params, "rows", $row);
        $this->rows = $trimArray($rows, ['{{label}}']);

        $column = Util::get($params, "column", '');
        $columns = Util::get($params, "columns", $column);
        $this->columns = $trimArray($columns, ['{{all}}']);

        $keys = array_keys($params);
        foreach ($keys as $key) {
            $op = trim(strtolower($key));
            if (!in_array($op, $this->operators)) {
                continue;
            }

            $aggFields = $trimArray($params[$op]);
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

        foreach (array('rows', 'columns') as $d) {
            $this->nodeToIndex[$d] = array();
            $this->indexToNode[$d] = array();
        }

        $totalNode = '{{all}}';
        foreach ($this->columns as $cf) {
            $this->nodeToIndex['columns'][$cf] = [$totalNode => 0];
            $this->indexToNode['columns'][$cf] = [0 => $totalNode];

            $this->data[$cf] = [];
            $this->count[$cf] = [];
        }
    }

    public function onInput($row)
    {
        $indexes = array();

        $rowNode = [];
        foreach ($this->rows as $rf) {
            $rowNode[] = $rf === '{{label}}' ?
            'Total' : Util::get($row, $rf, '{{others}}');
        }

        $rowNodeName = implode(" : ", $rowNode);
        if (!isset($this->nodeToIndex['rows'][$rowNodeName])) {
            $index = count($this->nodeToIndex['rows']);
            $this->nodeToIndex['rows'][$rowNodeName] = $index;
            $this->indexToNode['rows'][$index] = $rowNode;
        }
        $indexes['rows'] = $this->nodeToIndex['rows'][$rowNodeName];

        $indexes['columns'] = [];
        foreach ($this->columns as $cf) {
            if ($cf === '{{all}}') {
                continue;
            }

            Util::init($indexes['columns'], $cf, []);
            $colNode = Util::get($row, $cf, '{{others}}');
            if (!isset($this->nodeToIndex['columns'][$cf][$colNode])) {
                $index = count($this->nodeToIndex['columns'][$cf]);
                $this->nodeToIndex['columns'][$cf][$colNode] = $index;
                $this->indexToNode['columns'][$cf][$index] = $colNode;
            }
            $indexes['columns'][$cf][] = $this->nodeToIndex['columns'][$cf][$colNode];
        }

        //0 is index of the {{all}} node
        $rowNodeIndex = !empty($this->rows) ? $indexes['rows'] : 0;

        //0 is index of the {{all}} node
        $colNodes = [];
        foreach ($this->columns as $cf) {
            $colNodes[$cf] = [0];
        }

        // if (! empty($this->columns))
        foreach ($indexes['columns'] as $cf => $colNodeIndexes) {
            foreach ($colNodeIndexes as $colNodeIndex) {
                $colNodes[$cf][] = $colNodeIndex;
            }
        }

        //Each data node is a combination of a row node index and a column node one
        $dataNodes = array_fill_keys($this->columns, []);
        foreach ($colNodes as $cf => $colNodeIndexes) {
            foreach ($colNodeIndexes as $colNodeIndex) {
                $dataNodes[$cf][] = $rowNodeIndex . ' : ' . $colNodeIndex;
            }
        }

        //init and aggregate value for each data node
        $data = &$this->data;
        $count = &$this->count;
        $countAll = &$this->countAll;
        $sumAll = &$this->sumAll;
        foreach ($this->aggregates as $af => $operators) {
            if (!isset($row[$af])) {
                continue;
            }

            foreach ($dataNodes as $cf => $dNodes) {
                foreach ($dNodes as $dNode) {
                    Util::init($data[$cf], $dNode, []);
                    Util::init($data[$cf][$dNode], $af, []);
                    $datum = &$data[$cf][$dNode][$af];
                    foreach ($operators as $op) {
                        Util::init($datum, $op, $this->initValue($op));
                        $datum[$op] =
                        $this->aggValue($op, $datum[$op], $row[$af]);
                    }
                    unset($datum);
                    if (isset($this->hasAvg[$af])) {
                        Util::init($count[$cf], $dNode, []);
                        Util::init($count[$cf][$dNode], $af, $this->initValue('count'));
                        $count[$cf][$dNode][$af] += 1;
                    }
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
        unset($data, $count, $countAll, $sumAll);
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

    public function buildAggColName($cf, $colNode, $af, $operator)
    {
        return "$cf - $colNode | $af - $operator";
    }

    public function finalize()
    {
        //set meta for the 1st/label column of aggregated output
        $cMetas = array();
        $this->forwardMeta = array('columns' => &$cMetas);
        $labelColumns = !empty($this->rows) ? $this->rows : ['{{label}}'];
        foreach ($labelColumns as $labelColumn) {
            $cMetas[$labelColumn] = array('type' => 'string');
        }

        //set meta for aggregated columns
        foreach ($this->aggregates as $af => $operators) {
            foreach ($operators as $op) {
                foreach ($this->indexToNode['columns'] as $cf => $colNodes) {
                    foreach ($colNodes as $colNode) {
                        $aggColName = $this->buildAggColName($cf, $colNode, $af, $op);
                        if ($op === 'count') {
                            $cMetas[$aggColName] = ['type' => 'number'];
                        } else if ($op === 'count percent' || $op === 'sum percent') {
                            $cMetas[$aggColName] = [
                                'type' => 'number',
                                'decimals' => 2,
                                'suffix' => '%',
                            ];
                        } else {
                            $meta = Util::get($this->metaData, 'columns', []);
                            $cMetas[$aggColName] = Util::get($meta, $af, []);
                        }
                    }
                }
            }
        }
        unset($cMetas);

        // if aggregate operator is average, divide total sum by total count
        foreach ($this->data as $cf => &$data) {
            foreach ($data as $dn => &$nodeValues) {
                foreach ($nodeValues as $af => &$datum) {
                    if (isset($this->hasAvg[$af])) {
                        $datum['avg'] *= 1 / $this->count[$cf][$dn][$af];
                    }
                    if (isset($this->hasCountPercent[$af])) {
                        $datum['count percent'] *= 100 / $this->countAll[$af];
                    }
                    if (isset($this->hasSumPercent[$af])) {
                        $datum['sum percent'] *= 100 / $this->sumAll[$af];
                    }
                }
            }
        }
        //Delete these reference variables, otherwise later variables
        //of the same names would cause so much trouble
        unset($data, $nodeValues, $datum);

        //convert aggregated data to rows form
        $rows = array();
        foreach ($this->indexToNode['rows'] as $ri => $rowNode) {
            $row = [];
            $labelValues = !empty($this->rows) ? $rowNode : ['Total'];
            foreach ($labelColumns as $c => $labelColumn) {
                $row[$labelColumn] = $labelValues[$c];
            }

            foreach ($this->indexToNode['columns'] as $cf => $colNodes) {
                foreach ($colNodes as $ci => $colNode) {
                    $dNode = "$ri : $ci";
                    $arr = Util::get($this->data[$cf], $dNode, $this->aggregates);
                    foreach ($arr as $af => $opsOrDatum) {
                        foreach ($opsOrDatum as $k => $v) {
                            list($op, $value) = isset($this->data[$cf][$dNode]) ?
                            [$k, $v] : [$v, $this->emptyValue];
                            $aggColName = $this->buildAggColName($cf, $colNode, $af, $op);
                            $row[$aggColName] = $value;
                        }
                    }
                }
            }
            $rows[] = $row;
        }
        $this->data = &$rows;
    }

    public function receiveMeta($metaData, $source)
    {
        $this->metaData = array_merge($this->metaData, $metaData);
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
