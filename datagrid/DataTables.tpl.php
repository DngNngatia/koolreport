<?php
	use \koolreport\core\Utility as Util;
	$tableCss = Util::get($this->cssClass,"table");
	$trClass = Util::get($this->cssClass,"tr");
	$tdClass = Util::get($this->cssClass,"td");
	$thClass = Util::get($this->cssClass,"th");
    $tfClass = Util::get($this->cssClass,"tf");

    $draw = (int) Util::get($this->submitType, 'draw', 0);
    $id = Util::get($this->submitType, 'id', null);
    $ds = $this->dataStore;
    $ajax = $this->serverSide && $id == $this->name;
    if ($ajax) {
        echo "<!-- dt_{$this->name}_start -->";
        $resData = [
            'draw' => $draw + 1,
            'recordsTotal' => Util::get($meta, 'totalRecords', 0),
            'recordsFiltered' => Util::get($meta, 'filterRecords', 0),
            'data' => $ds->data()
        ];
        echo json_encode($resData);
        echo "<!-- dt_{$this->name}_end -->";
    }
?>
<table id="<?php echo $this->name; ?>"
<?php echo ($tableCss)?" class='$tableCss'":" class='table display'"; ?> >
    <thead>
        <tr>
        <?php
        foreach($showColumnKeys as $cKey)
        {
            $cMeta = Util::get($meta["columns"], $cKey, []);
            $label = Util::get($cMeta,"label", $cKey);
        ?>
            <th <?php if($thClass){echo " class='".((gettype($thClass)=="string")?$thClass:$thClass($cKey))."'";} ?>>
                <?php echo $label; ?>
            </th>
        <?php    
        }
        ?>  
        </tr>  
    </thead>
    <?php if (! $this->serverSide) { ?>
    <tbody>
        <?php
        $this->dataStore->popStart();
        while($row = $this->dataStore->pop())
        {
            $i=$this->dataStore->getPopIndex();
        ?>
            <tr <?php if($trClass){echo " class='".((gettype($trClass)=="string")?$trClass:$trClass($row))."'";} ?>>
            <?php
            foreach($showColumnKeys as $cKey)
            {
                $cMeta = $meta['columns'][$cKey];
            ?>
                <td <?php if($tdClass)
                    echo "class='".
                        (gettype($tdClass)=="string"?$tdClass:$tdClass($row,$cKey))."'"; 
                    foreach (['data-order', 'data-search'] as $d)
                        if (isset($cMeta[$d]))
                            echo "$d='".Util::get($row, $cMeta[$d], '')."'";
                ?>>
                    <?php 
                        $formatValue = Util::get($cMeta,"formatValue",null);
                        $value = $cKey!=="#" ? Util::get($row, $cKey, $this->emptyValue) 
                            :($i+$cMeta["start"]);
                        if (isset($row[$cKey]) || is_callable($formatValue))
                            echo $this->formatValue($value,$cMeta,$row);
                        else
                            echo $this->emptyValue;
                    ?>
                </td>
            <?php    
            }
            ?>    
            </tr>
        <?php    
        }
        ?>
    </tbody>
    <?php } ?>
    <?php
    if($this->showFooter)
    {
    ?>
    <tfoot>
        <tr>
            <?php
            foreach($showColumnKeys as $cKey)
            {
            ?>
                <td <?php if($tfClass){echo " class='".((gettype($tfClass)=="string")?$tfClass:$tfClass($cKey))."'";} ?>>
                <?php
                $footerMethod = strtolower(Util::get($meta["columns"][$cKey],"footer"));
                $footerText = Util::get($meta["columns"][$cKey],"footerText");
                $footerValue = null;
                switch($footerMethod)
                {
                    case "sum":
                    case "min":
                    case "max":
                    case "avg":
                        $footerValue = $this->dataStore->$footerMethod($cKey);
                    break;
                    case "count":
                        $footerValue = $this->dataStore->countData();
                    break;
                }
                $footerValue = ($footerValue!==null)?$this->formatValue($footerValue,$meta["columns"][$cKey]):"";
                if($footerText)
                {
                    echo str_replace("@value",$footerValue,$footerText);
                }
                else
                {
                    echo $footerValue;
                }
                ?>
                </td>
            <?php    
            }
            ?>
        </tr>
    </tfoot>
    <?php
    }
    ?>
</table>
<script type="text/javascript">
    KoolReport.widget.init(
        <?php echo json_encode($this->getResources()); ?>,
        function() {
            <?php echo $this->name; ?> = $('#<?php echo $this->name; ?>').DataTable(<?php echo ($this->options==array())?"":Util::jsonEncode($this->options); ?>);
            <?php
            if($this->clientEvents)
            {
                foreach($this->clientEvents as $eventName=>$function)
                {
                ?>
                    <?php echo $this->name; ?>.on("<?php echo $eventName; ?>",<?php echo $function; ?>)
                <?php    
                }
            }
            ?>
            <?php $this->clientSideReady();?>
        }
    );
</script>