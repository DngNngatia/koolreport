<?php
namespace koolreport\pivot;

use \koolreport\core\Utility as Util;

class PivotUtil
{
    protected $dataStore;
    protected $params;

    protected $measures;
    protected $rowDimension;
    protected $columnDimension;
    protected $rowSort;
    protected $columnSort;
    protected $headerMap;
    protected $dataMap;
    protected $totalName;
    protected $hideTotalRow;
    protected $hideTotalColumn;
    protected $paging;

    protected $FieldsNodesIndexes;

    public function __construct($dataStore, $params)
    {
        $this->dataStore = $dataStore;
        $this->params = $params;

        $this->rowDimension = Util::get($this->params, 'rowDimension', 'row');
        $this->columnDimension = Util::get($this->params, 'columnDimension', 'column');
        $this->rowSort = Util::get($this->params, 'rowSort', []);
        $this->columnSort = Util::get($this->params, 'columnSort', []);
        $this->headerMap = Util::get($this->params, 'headerMap',
            function ($v, $f) {return $v;});
        $this->map = Util::get($this->params, 'map', []);
        $this->dataMap = Util::get($this->params, 'dataMap', null);
        $this->totalName = Util::get($this->params, 'totalName', 'Total');
        $this->hideTotalRow = Util::get($this->params, 'hideTotalRow', false);
        $this->hideTotalColumn = Util::get($this->params, 'hideTotalColumn', false);

        //Get the measure field and settings in format
        $measures = [];
        $mSettings = Util::get($this->params, 'measures', []);
        $meta = $dataStore->meta()['columns'];
        foreach ($mSettings as $cKey => $cValue) {
            if (gettype($cValue) == 'array') {
                $measures[$cKey] = $cValue;
            } else {
                $measures[$cValue] = isset($meta[$cValue]) ? $meta[$cValue] : null;
            }

        }
        if (empty($measures)) {
            $dataStore->popStart();
            $row = $dataStore->pop();
            if (empty($row)) {
                $columns = [];
            } else {
                $columns = array_keys($row);
                foreach ($columns as $c) {
                    if ($meta[$c]['type'] !== 'dimension') {
                        $measures[$c] = $meta[$c];
                    }
                }

            }
        }
        $this->measures = $measures;
        $this->waitingFields = Util::get($this->params, 'waitingFields', []);
        $this->process();
    }

    protected function sort(& $index, $sortInfo) 
    {
        $compareFunc = function ($a, $b) use ($sortInfo) {
            foreach ($sortInfo as $k => $v)
                $$k = $v;
            $cmp = 0;
            $parentNode = [];
            foreach ($fields as $field) {
                $parentNode[$field] = '{{all}}';
            }

            foreach ($fields as $field) {
                $value1 = $nodes[$a][$field];
                $value2 = $nodes[$b][$field];
                $node1 = $node2 = $parentNode;
                $node1[$field] = $value1;
                $node2[$field] = $value2;
                if ($value1 === $value2) {
                    $parentNode[$field] = $value1;
                    continue;
                } else if ($value1 === '{{all}}') {
                    return $sortTotalFirst ? -1 : 1;
                } else if ($value2 === '{{all}}') {
                    return $sortTotalFirst ? 1 : -1;
                } else {
                    $cmp = is_numeric($value1) && is_numeric($value2) ?
                    $value1 - $value2 : strcmp($value1, $value2);
                    $sortField = isset($sort[$field]) ? $sort[$field] : null;
                    if (is_string($sortField)) {
                        $cmp = $sortField === 'desc' ? -$cmp : $cmp;
                    } else if (is_callable($sortField)) {
                        $cmp = $sortField($value2, $value1);
                    }
                }
                if ($cmp !== 0) {
                    break;
                }

            }
            $dataCmp = $cmp;
            foreach ($dataFields as $field) {
                if (isset($sort[$field]) && $sort[$field] !== 'ignore') {
                    $dataSortField = $field;
                    $dataSortDirection = $sort[$field];
                    break;
                }
            }

            $index1 = $nameToIndex[implode(' - ', $node1)];
            $index2 = $nameToIndex[implode(' - ', $node2)];
            if (isset($dataSortField) &&
                isset($dimIndexToData[$index1][$dataSortField])) {
                $sortValue1 = isset($dimIndexToData[$index1]) ?
                    $dimIndexToData[$index1][$dataSortField] : 0;
                $sortValue2 = isset($dimIndexToData[$index2]) ?
                    $dimIndexToData[$index2][$dataSortField] : 0;
                $diff = $sortValue1 - $sortValue2;
                if ($dataSortDirection === 'asc') {
                    $dataCmp = $diff;
                } else if ($dataSortDirection === 'desc') {
                    $dataCmp = -$diff;
                } else if (is_callable($dataSortDirection)) {
                    $dataCmp = $dataSortDirection($sortValue1, $sortValue2);
                }
            }
            return $dataCmp;
        };
        
        usort($index, $compareFunc);
    }

    protected function computeNodesInfo($nodes, $fields, $indexes, $hideTotal) 
    {
        $fieldInfo = array_fill_keys($fields, []);
        $nodesInfo = array_fill(0, count($nodes), $fieldInfo);
        $numChildren = array_fill_keys($fields, 1);
        $numLeaf = array_fill_keys($fields, 1);
        $childOrder = array_fill_keys($fields, 0);
        $nullNode = array_fill_keys($fields, null);
        $lastSameValueIndex = array_fill_keys($fields, $indexes[0]);
        array_push($indexes, count($indexes));
        $prevNode = $nullNode;
        foreach ($indexes as $i => $index) {
            $node = Util::get($nodes, $index, $nullNode);
            $seenTotalCell = false;
            foreach ($fields as $j => $f) {
                if ($node[$f] !== $prevNode[$f]) {
                    $lsvi = $lastSameValueIndex[$f];
                    if ($nodes[$lsvi][$f] !== '{{all}}') {
                        $nodesInfo[$lsvi][$f]['numChildren'] = $numChildren[$f];
                        $nodesInfo[$lsvi][$f]['numLeaf'] = $numLeaf[$f];
                    }
                    $lastSameValueIndex[$f] = $index;
                    $numChildren[$f] = 1;
                    $numLeaf[$f] = 1;

                    $childOrder[$f] += 1;
                    $childOrders = '';
                    for ($k=0; $k<=$j; $k++) {
                        $childOrders .= ($childOrder[$fields[$k]]) . ".";
                    }
                    $childOrders = substr($childOrders, 0, -1);
                    $nodesInfo[$index][$f]['childOrder'] = $childOrders;
                } else {
                    $numChildren[$f] += 1;
                    $numLeaf[$f] += 1;
                }

                if ($node[$f] === '{{all}}') {
                    $nodesInfo[$index][$f]['total'] = true;
                    $nodesInfo[$index]['hasTotal'] = true;
                    $childOrder[$f] = 0;
                }

                if (! $seenTotalCell && $node[$f] === '{{all}}') {
                    $seenTotalCell = true;
                    $nodesInfo[$index][$f]['numChildren'] = 1;
                    $nodesInfo[$index][$f]['numLeaf'] = 1;
                    $nodesInfo[$index][$f]['level'] = count($fields) - $j;
                    $nodesInfo[$index]['fieldOrder'] = $j - 1;

                    $prevField = $j > 0 ? $fields[$j - 1] : '';
                    $parent = Util::get($node, $prevField, null);
                    $prevParent = Util::get($prevNode, $prevField, null);
                    if ($parent !== $prevParent) continue;
                    for ($k = 0; $k < $j; $k++) {
                        $prevF = $fields[$k];
                        $numLeaf[$prevF] -= 1;
                    }
                }

            }
            if (! $seenTotalCell) {
                $nodesInfo[$index]['fieldOrder'] = count($fields) - 1;
            }
            $prevNode = $node;
        }
        array_pop($indexes);
        if ($hideTotal) {
            array_pop($indexes);
        }
        // Util::prettyPrint($nodesInfo);
        return $nodesInfo;
    }

    protected function getMappedFields($dimension, $fields)
    {
        $fieldMap = Util::get($this->map, $dimension . 'Field', null);
        if (! isset($fieldMap)) return null;
        $fieldMap = function ($v, $info) use ($fieldMap) {
            if (is_array($fieldMap)) {
                return isset($fieldMap[$v]) ? $fieldMap[$v] : $v;
            }
            return $fieldMap($v, $info);
        };
        $fieldsInfo = [];
        foreach ($fields as $fi => $f) {
            $fieldsInfo[$f] = ['fieldOrder' => $fi];
        }
        $mappedFields = isset($fields[0]) && $fields[0] === 'root' ? [] : 
            array_combine($fields, array_map($fieldMap, $fields, $fieldsInfo));
        return $mappedFields;
    }

    protected function getMappedNodes($dimension, $nodes, $nodesInfo)
    {
        $nodeMap = Util::get($this->map, $dimension . 'Header', null);
        if (! isset($nodeMap)) return null;
        $totalName = $this->totalName;
        $nodeMap = function ($v, $info) use ($nodeMap, $totalName) {
            if ($v === '{{all}}') {
                return $totalName;
            }
            if (is_array($nodeMap)) {
                return isset($nodeMap[$v]) ? $nodeMap[$v] : $v;
            }
            return $nodeMap($v, $info);
        };
        $mappedNodes = [];
        foreach ($nodes as $i => $node) {
            // $nodeInfo = $nodesInfo[$i];
            $fields = array_keys($node);
            foreach ($fields as $fi => $f) {
                $nodeInfo[$f] = $nodesInfo[$i][$f];
                $nodeInfo[$f]['fieldName'] = $f;
                $nodeInfo[$f]['fieldOrder'] = $fi;
            }
            $mappedNodes[$i] = array_map($nodeMap, $node, $nodeInfo);
            $mappedNodes[$i] = array_combine($fields, $mappedNodes[$i]);
        }
        return $mappedNodes;
    }

    protected function getMappedData($indexToData, $rowNodesInfo, $colNodesInfo)
    {
        $cellMap = Util::get($this->map, 'dataCell', null);
        if (! isset($cellMap)) return null;
        $cellMap = function ($v, $info) use ($cellMap) {
            if (is_array($cellMap)) {
                return isset($cellMap[$v]) ? $cellMap[$v] : $v;
            }
            else if (is_callable($cellMap)) {
                return $cellMap($v, $info);
            }
            return $v;
        };
        $dataFields = $this->dataFields;
        $indexToMappedData = [];
        $cMetas = $this->dataStore->meta()['columns'];
        foreach ($indexToData as $ri => $cis) {
            $rowNodeInfo = $rowNodesInfo[$ri];
            $indexToMappedData[$ri] = [];
            foreach ($cis as $ci => $dataNode) {
                $colNodeInfo = $colNodesInfo[$ci];
                $nodeInfo = [
                    'row' => $rowNodeInfo,
                    'column' => $colNodeInfo
                ];
                $cellInfo = [];
                $node = array_slice($dataNode, 0, count($dataFields));
                foreach ($dataFields as $di => $df) {
                    $cellInfo[$df] = $nodeInfo;
                    $cellInfo[$df]['fieldName'] = $df;
                    $cellInfo[$df]['fieldOrder'] = $di;
                    $cellInfo[$df]['formattedValue'] = 
                        Util::format($node[$df], $cMetas[$df]);
                }
                $node = array_map($cellMap, $node, $cellInfo);
                $indexToMappedData[$ri][$ci] = array_combine($dataFields, $node);
            }
        } 
        return $indexToMappedData;
    }

    protected function process()
    {
        if (! $this->dataStore) {
            return [];
        }

        $dataStore = $this->dataStore;
        $meta = $dataStore->meta()['columns'];
        $data = $dataStore->data();

        $rowDimension = isset($meta[$this->rowDimension]) ?
            $this->rowDimension : null;
        $columnDimension = isset($meta[$this->columnDimension]) ?
            $this->columnDimension : null;

        $rowNodes = isset($rowDimension) ?
            $meta[$rowDimension]['index'] : null;
        $colNodes = isset($columnDimension) ?
            $meta[$columnDimension]['index'] : null;
        if (empty($rowNodes) || empty($rowNodes[0])) {
            $rowNodes = array(array('root' => '{{all}}'));
        }
        if (empty($colNodes) || empty($colNodes[0])) {
            $colNodes = array(array('root' => '{{all}}'));
        }

        $rowFields = $this->rowFields = array_keys($rowNodes[0]);
        $colFields = $this->colFields = array_keys($colNodes[0]);
        $dataFields = $this->dataFields = array_keys($this->measures);

        $nameToIndexRow = [];
        foreach ($rowNodes as $i => $node) {
            $nameToIndexRow[implode(' - ', $node)] = $i;
        }

        $nameToIndexCol = [];
        foreach ($colNodes as $i => $node) {
            $nameToIndexCol[implode(' - ', $node)] = $i;
        }

        $rowIndexToData = [];
        $colIndexToData = [];
        $indexToData = [];
        
        foreach ($data as $dataRow) {
            $rowIndex = (int) Util::get($dataRow, $rowDimension, 0);
            $colIndex = (int) Util::get($dataRow, $columnDimension, 0);
            if (isset($rowDimension) && $colIndex === 0) {
                $rowIndexToData[$rowIndex] = $dataRow;
            }

            if (isset($columnDimension) && $rowIndex === 0) {
                $colIndexToData[$colIndex] = $dataRow;
            }

            $indexToData[$rowIndex][$colIndex] = $dataRow;
        }

        $rowIndexes = $rowIndexes2 = range(0, count($rowNodes) - 1);
        $colIndexes = range(0, count($colNodes) - 1);
        $rowSortInfo = [
            'nodes' => $rowNodes,
            'fields' => $rowFields,
            'nameToIndex' => $nameToIndexRow, 
            'dimIndexToData' => $rowIndexToData, 
            'sort' => $this->rowSort, 
            'dataFields' => $dataFields,
            'sortTotalFirst' => false
        ];
        $this->sort($rowIndexes, $rowSortInfo);
        $rowSortInfo['sortTotalFirst'] = true;
        $this->sort($rowIndexes2, $rowSortInfo);
        $colSortInfo = [
            'nodes' => $colNodes,
            'fields' => $colFields,
            'nameToIndex' => $nameToIndexCol, 
            'dimIndexToData' => $colIndexToData, 
            'sort' => $this->columnSort, 
            'dataFields' => $dataFields,
            'sortTotalFirst' => false
        ];
        $this->sort($colIndexes, $colSortInfo);

        $rowNodesInfo = $this->computeNodesInfo(
            $rowNodes, $rowFields, $rowIndexes, $this->hideTotalRow);

        $rowNodesInfo2 = $this->computeNodesInfo(
            $rowNodes, $rowFields, $rowIndexes2, $this->hideTotalRow);

        $colNodesInfo = $this->computeNodesInfo(
            $colNodes, $colFields, $colIndexes, $this->hideTotalColumn);
        
        $numDf = count($dataFields) > 0 ? count($dataFields) : 1;
        foreach ($colNodesInfo as $i => $mark) {
            foreach ($mark as $f => $fInfo) {
                if (! isset($fInfo['numChildren'])) continue;
                $colNodesInfo[$i][$f]['numChildren'] *= $numDf;
                $colNodesInfo[$i][$f]['numLeaf'] *= $numDf;
            }
        }

        $totalName = $this->totalName;
        $headerMap = $this->headerMap;
        $headerMap = function ($v, $f) use ($headerMap, $totalName) {
            if ($v === '{{all}}') {
                return $totalName;
            }
            if (is_array($headerMap)) {
                return isset($headerMap[$v]) ? $headerMap[$v] : $v;
            }
            return $headerMap($v, $f);
        };
        $mappedRowNodes = $mappedRowNodes2 = [];
        foreach ($rowNodes as $i => $node) {
            $mappedRowNodes[$i] = $mappedRowNodes2[$i] = array_combine($rowFields,
                array_map($headerMap, $node, $rowFields));
        }
        $mappedColNodes = [];
        foreach ($colNodes as $i => $node) {
            $mappedColNodes[$i] = array_combine($colFields,
                array_map($headerMap, $node, $colFields));
        }
        $waitingFields = array_keys($this->waitingFields);
        $mappedDataFields = array_combine($dataFields,
            array_map($headerMap, $dataFields, [], []));
        $mappedColFields = $colFields[0] !== 'root' ? array_combine($colFields,
            array_map($headerMap, $colFields, [], [])) : [];
        $mappedRowFields = $rowFields[0] !== 'root' ? array_combine($rowFields,
            array_map($headerMap, $rowFields, [], [])) : [];
        $mappedWaitingFields = array_combine($waitingFields,
            array_map($headerMap, $waitingFields, [], []));

        $mappedFields = $this->getMappedFields('row', $rowFields);
        if (isset($mappedFields)) $mappedRowFields = $mappedFields;
        $mappedFields = $this->getMappedFields('column', $colFields);
        if (isset($mappedFields)) $mappedColFields = $mappedFields;
        $mappedFields = $this->getMappedFields('data', $dataFields);
        if (isset($mappedFields)) $mappedDataFields = $mappedFields;
        $mappedFields = $this->getMappedFields('waiting', $waitingFields);
        if (isset($mappedFields)) $mappedWaitingFields = $mappedFields;

        $mappedNodes = $this->getMappedNodes('row', $rowNodes, $rowNodesInfo);
        if (isset($mappedNodes)) $mappedRowNodes = $mappedNodes;
        $mappedNodes = $this->getMappedNodes('row', $rowNodes, $rowNodesInfo2);
        if (isset($mappedNodes)) $mappedRowNodes2 = $mappedNodes;
        $mappedNodes = $this->getMappedNodes('column', $colNodes, $colNodesInfo);
        if (isset($mappedNodes)) $mappedColNodes = $mappedNodes;

        $dataMap = $this->dataMap;
        if (is_array($dataMap)) {
            $dataMap = function ($v) use ($dataMap) {
                return isset($dataMap[$v]) ? $dataMap[$v] : $v;
            };
        }
        $indexToMappedData = $indexToData;
        foreach ($indexToMappedData as $ri => $cis) {
            foreach ($cis as $ci => $d) {
                if (is_callable($dataMap)) {
                    $indexToMappedData[$ri][$ci] = array_combine(array_keys($d),
                        array_map($dataMap, $d, array_keys($d)));
                } else {
                    foreach ($d as $df => $v) {
                        $indexToMappedData[$ri][$ci][$df] = Util::format($v,
                            Util::get($this->measures, $df, $meta[$df]));
                    }
                }
            }
        }    
        $mappedData = $this->getMappedData($indexToData, $rowNodesInfo, $colNodesInfo);
        if (isset($mappedData)) $indexToMappedData = $mappedData;    


        $waitingFieldsType = array_values($this->waitingFields);
        $dataFieldsType = array_fill(0, count($dataFields), 'data');
        $columnFieldsType = array_fill(0, count($colFields), 'column');
        $rowFieldsType = array_fill(0, count($rowFields), 'row');

        $waitingFieldsSort = array_fill(0, count($this->waitingFields), 'noSort');
        $dataFieldsSort = array_fill(0, count($dataFields), 'noSort');
        $columnFieldsSort = array_fill(0, count($colFields), 'noSort');
        $rowFieldsSort = array_fill(0, count($rowFields), 'noSort');
        $colSortDataField = null;
        foreach ($this->columnSort as $field => $dir) {
            foreach ($dataFields as $i => $dataField) {
                if ($dataField === $field && ($dir === 'asc' || $dir === 'desc')) {
                    $dataFieldsSort[$i] .= ' columnsort' . $dir;
                    $colSortDataField = $field;
                }
            }

        }
        $rowSortDataField = null;
        foreach ($this->rowSort as $field => $dir) {
            foreach ($dataFields as $i => $dataField) {
                if ($dataField === $field && ($dir === 'asc' || $dir === 'desc')) {
                    $dataFieldsSort[$i] .= ' rowsort' . $dir;
                    $rowSortDataField = $field;
                }
            }
        }
        if (! $colSortDataField) {
            foreach ($this->columnSort as $field => $dir) {
                foreach ($colFields as $i => $colField) {
                    if ($colField == $field && ($dir === 'asc' || $dir === 'desc')) {
                        $columnFieldsSort[$i] = 'columnsort' . $dir;
                    }
                }
            }
        }
        if (! $rowSortDataField) {
            foreach ($this->rowSort as $field => $dir) {
                foreach ($rowFields as $i => $rowField) {
                    if ($rowField === $field && ($dir === 'asc' || $dir === 'desc')) {
                        $rowFieldsSort[$i] = 'rowsort' . $dir;
                    }
                }
            }
        }


        $numRow = count($rowNodes);
        $this->FieldsNodesIndexes = array(
            'waitingFields' => $waitingFields,
            'dataFields' => $dataFields,
            'colFields' => $colFields,
            'rowFields' => $rowFields,
            'waitingFieldsType' => $waitingFieldsType,
            'dataFieldsType' => $dataFieldsType,
            'columnFieldsType' => $columnFieldsType,
            'rowFieldsType' => $rowFieldsType,
            'waitingFieldsSort' => $waitingFieldsSort,
            'dataFieldsSort' => $dataFieldsSort,
            'columnFieldsSort' => $columnFieldsSort,
            'rowFieldsSort' => $rowFieldsSort,
            'mappedDataFields' => $mappedDataFields,
            'mappedColFields' => $mappedColFields,
            'mappedRowFields' => $mappedRowFields,
            'mappedWaitingFields' => $mappedWaitingFields,
            'colNodes' => $colNodes,
            'rowNodes' => $rowNodes,
            'mappedColNodes' => $mappedColNodes,
            'mappedRowNodes' => $mappedRowNodes,
            'mappedRowNodes2' => $mappedRowNodes2,
            'colIndexes' => $colIndexes,
            'rowIndexes' => $rowIndexes,
            'rowIndexes2' => $rowIndexes2,
            'colNodesInfo' => $colNodesInfo,
            'rowNodesInfo' => $rowNodesInfo,
            'rowNodesInfo2' => $rowNodesInfo2,
            'indexToMappedData' => $indexToMappedData,
            'indexToData' => $indexToData,
            'numRow' => $numRow,
        );
    }

    public function getFieldsNodesIndexes()
    {
        return $this->FieldsNodesIndexes;
    }
}
