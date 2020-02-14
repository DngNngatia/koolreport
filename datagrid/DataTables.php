<?php

namespace koolreport\datagrid;

use \koolreport\core\Widget;
use \koolreport\core\Utility as Util;
use \koolreport\core\DataStore;

class DataTables extends Widget
{
    protected $name;
    protected $columns;
    protected $data;
    protected $options;
    protected $emptyValue = '-';
    protected $showFooter;
    protected $clientEvents;
    public function version()
    {
        return "2.0.0";
    }

    protected function resourceSettings()
    {
        switch($this->getThemeBase())
        {
            case "bs4":
                return array(
                    "library"=>array("jQuery"),
                    "folder"=>"DataTables",
                    "js"=>array(
                        "datatables.min.js",
                        array(
                            "pagination/input.js",
                            "datatables.bs4.min.js"
                        )
                    ),
                    "css"=>array("datatables.bs4.min.css")
                );    
            case "bs3":
            default:
                return array(
                    "library"=>array("jQuery"),
                    "folder"=>"DataTables",
                    "js"=>array("datatables.min.js", 
                        [
                            "pagination/input.js"
                        ]
                    ),
                    "css"=>array("datatables.min.css")
                );    
        }
    }

    protected function onInit()
    {
        $this->useLanguage();
        $scope = Util::get($this->params,"scope",array());
        $this->scope = is_callable($scope)?$scope():$scope;
        $this->useDataSource($this->scope);
        $this->name = Util::get($this->params,"name");
        $this->columns = Util::get($this->params,"columns",array());
        $this->showFooter = Util::get($this->params,"showFooter",false);
        $this->clientEvents = Util::get($this->params,"clientEvents",false);
        $this->serverSide = Util::get($this->params,"serverSide",false);
        $this->method = strtoupper(Util::get($this->params,"method",'get'));
        $this->submitType = $this->method === 'POST' ? $_POST : $_GET;
        if(!$this->name)
        {
            $this->name = "datatables".Util::getUniqueId();
        }

        if($this->dataStore==null)
        {
            throw new \Exception("dataSource is required for DataTables");
            return;	
        }


        $this->options = array(
            "searching"=>false,
            "paging"=>false,
        );
        

        if($this->languageMap!=null)
        {
            $this->options["language"] = $this->languageMap;
        }
        
        $this->options = array_merge($this->options,
            Util::get($this->params,"options",array()));
        $this->cssClass = Util::get($this->params,"cssClass",array());
    }


	protected function formatValue($value,$format,$row=null)
	{
        $formatValue = Util::get($format,"formatValue",null);

        if(is_string($formatValue))
        {
            eval('$fv="'.str_replace('@value','$value',$formatValue).'";');
            return $fv;
        }
        else if(is_callable($formatValue))
        {
            return $formatValue($value,$row);
        }
		else
		{
			return Util::format($value,$format);
		}
	}
    protected function onRender()
    {
        $meta = $this->dataStore->meta();
		if (! isset($meta['columns'])) $meta['columns'] = [];
		$cMetas = $meta["columns"];
		
        $showColumnKeys = array();
        if($this->columns==array())
        {
            $this->dataStore->popStart();
            $row = $this->dataStore->pop();
            if($row) {
                $showColumnKeys = array_keys($row);
            }
			else {
				$showColumnKeys = array_keys($cMetas);
			}
        }
        else
        {
            foreach($this->columns as $cKey=>$cValue)
            {
                if(gettype($cValue)=="array")
                {
                    if($cKey==="#")
                    {
                        $cMetas[$cKey] = array(
                            "type"=>"number",
                            "label"=>"#",
                            "start"=>1,
                        );
                    }
                    if (! isset($cMetas[$cKey])) $cMetas[$cKey] = [];
                    $cMetas[$cKey] =  array_merge($cMetas[$cKey],$cValue);                
                    if(!in_array($cKey,$showColumnKeys))
                    {
                        array_push($showColumnKeys,$cKey);
                    }
                }
                else
                {
                    if($cValue==="#")
                    {
                        $cMetas[$cValue] = array(
                            "type"=>"number",
                            "label"=>"#",
                            "start"=>1,
                        );
                    }
                    if(!in_array($cValue,$showColumnKeys))
                    {
                        array_push($showColumnKeys,$cValue);
                    }
                }    
            }
        }
        $meta["columns"] = $cMetas;
        // Util::prettyPrint($meta);
        
        if ($this->serverSide) {
            $columnsData = [];
            foreach ($showColumnKeys as $colKey)
                $columnsData[] = ['data' => $colKey];
            $scopeJson = json_encode($this->scope);
            $this->options = array_merge($this->options, [
                'serverSide' => true,
                'ajax' => [
                    'url' => '',
                    'data' => "function(d) {
                        d.id = '{$this->name}';
                        d.scope = {$scopeJson};
                    }",
                    'type' => "{$this->method}",
                    'dataFilter' => "function(data) {
                        var markStart = '<!-- dt_".$this->name."_start -->';
                        var markEnd = '<!-- dt_".$this->name."_end -->';
                        var start = data.indexOf(markStart);
                        var end = data.indexOf(markEnd);
                        var s = data.substring(start + markStart.length, end);
                        return s;
                    }",
                ],
                "columns" => $columnsData
            ]);
        }

		$this->template("DataTables",array(
			"showColumnKeys"=>$showColumnKeys,
			"meta"=>$meta,
        ));
    }

    protected function onFurtherProcessRequest($node)
	{
        $this->serverSide = Util::get($this->params,"serverSide",false);
        $this->method = Util::get($this->params,"method",'get');
        if (! $this->serverSide) {
            return $node;
        }
        function getFinalSources($node) {
            $finalSources = [];
            $sources = [];
            $index = 0;
            while ($source = $node->previous($index)) {
                $sources[] = $source;
                $index++;
            }
            if (empty($sources)) {
                return [$node];
            }
            foreach ($sources as $source) {
                $finalSources = array_merge($finalSources,
                    getFinalSources($source));
            }
            return $finalSources;
        }
        $queryParams = self::parseRequest($this->name, $this->method);
        $finalSources = getFinalSources($node);
        foreach ($finalSources as $finalSource) {
            if (method_exists($finalSource, 'queryProcessing')) {
                $finalSource->queryProcessing($queryParams);
            }
        }
		return $node;
	}

    public static function parseRequest($dtId, $method = 'get') 
    {
        $queryParams = [
            'start' => 0,
            'length' => 0,
        ];
        $request = $method === 'post' ? $_POST : $_GET;
        $id = Util::get($request, 'id', null);
        if ($id == $dtId) {
            $searchSql = "1=1";
            $columns = Util::get($request, 'columns', []);
            $searchColsSql = "1=1";
            foreach ($columns as $col) {
                $searchCol = Util::get($col, 'search', []);
                $searchColVal = Util::get($searchCol, 'value', null);
                $searchColsSql .= empty($searchColVal) ? 
                    "" : " AND {$col['data']} like '%{$searchColVal}%'";
            }
            $searchSql .= " AND ($searchColsSql)";

            $searchAll = Util::get($request, 'search', []);
            $searchAllValue = Util::get($searchAll, 'value', null);
            $searchAllSql = "";
            foreach ($columns as $col) {
                $searchAllSql .= empty($searchAllValue) ? 
                    "" : "{$col['data']} like '%{$searchAllValue}%' OR ";
            }
            $searchAllSql = ! empty($searchAllSql) ? 
                substr($searchAllSql, 0, -4) : "1=1";
            $searchSql .= " AND ($searchAllSql)";
            $queryParams['search'] = $searchSql;

            $orders = Util::get($request, 'order', []);
            $orderSql = "";
            foreach ($orders as $order) {
                $col = $columns[$order['column']]['data'];
                $dir = $order['dir'];
                $orderSql .= $col . " " . $dir . ",";
            }
            if (! empty($orderSql)) {
                $orderSql = substr($orderSql, 0, -1);
                $queryParams['order'] = $orderSql;
            }

            $start = (int) Util::get($request, 'start', null);
            $length = (int) Util::get($request, 'length', null);
            $queryParams['start'] = $start;
            $queryParams['length'] = $length;

            $queryParams['countTotal'] = true;
            $queryParams['countFilter'] = true;
        }
        return $queryParams;
    }
}