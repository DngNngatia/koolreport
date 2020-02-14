<?php
    use \koolreport\core\Utility as Util; 
?>
<div class='krpmDataZoneDiv'>
    <table class='table'>
        <colgroup>
            <?php foreach ($colIndexes as $c => $j) { 
                $colNodeInfo = $colNodesInfo[$j];
                $colClass = '';
                if (isset($colNodeInfo['hasTotal'])) 
                    $colClass = $colNodeInfo['fieldOrder'] === -1 ?
                        ' krpmDataCellColumnColGrandTotal' :
                        ' krpmDataCellColumnColTotal'; 

                foreach ($dataFields as $df) 
                    echo "<col class='$colClass'>";
            } ?>
        </colgroup>
        <tbody>
            <?php foreach($rowIndexes2 as $r => $i) { 
                $rowNodeInfo = $rowNodesInfo[$i]; ?>
                <tr class='krpmRow'
                    data-row-field=<?=$rowNodeInfo['fieldOrder']?>
                    style='display:'
                >
                    <?php foreach ($colIndexes as $c => $j) {
                        $colNodeInfo = $colNodesInfo[$j];
                        $dataRow = Util::get($indexToData, [$i, $j], []);
                        $mappedDataRow = Util::get($indexToMappedData, [$i, $j], []);
                        foreach($dataFields as $di => $df) { 
                            $value = Util::get($dataRow, $df, null); ?>
                            <td class='krpmDataCell 
                                <?php
                                    if (isset($colNodeInfo['hasTotal'])) 
                                        echo $colNodeInfo['fieldOrder'] === -1 ?
                                            ' krpmDataCellColumnGrandTotal' :
                                            ' krpmDataCellColumnTotal';

                                    if (isset($rowNodeInfo['hasTotal'])) 
                                        echo $rowNodeInfo['fieldOrder'] === -1 ?
                                            ' krpmDataCellRowGrandTotal' :
                                            ' krpmDataCellRowTotal';

                                    echo ' ' . $dcClass($df, $value);
                                ?>' 
                                data-data-field=<?=$di?>
                                data-column-field=<?=$colNodeInfo['fieldOrder']?>
                                data-row-field=<?=$rowNodeInfo['fieldOrder']?>
                                data-row-index=<?=$r;?>
                                data-column-index=<?=$c;?>
                                data-row-layer=1
                                data-column-layer=1
                                data-page-layer=1
                                style='display:' 
                            >
                                <span class='krpmDataCellText'>
                                    <?php echo Util::get($mappedDataRow, $df, $emptyValue); ?>
                                </span>
                            </td>
                        <?php } ?>
                    <?php } ?>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>