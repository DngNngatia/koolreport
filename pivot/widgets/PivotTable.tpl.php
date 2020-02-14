<?php 
use \koolreport\core\Utility as Util;
$chClass = Util::get($cssClass, "columnHeader", "");
if (is_string($chClass)) $chClass = function() use ($chClass) {return $chClass;};
$rhClass = Util::get($cssClass, "rowHeader", "");
if (is_string($rhClass)) $rhClass = function() use ($rhClass) {return $rhClass;};
$dcClass = Util::get($cssClass, "dataCell", "");
if (is_string($dcClass)) $dcClass = function() use ($dcClass) {return $dcClass;};
$numDf = count($dataFields);
?>
<style>

    .fa {
        cursor: pointer;
    }

    .pivot-row {
        white-space: nowrap;
    }

    #<?php echo $this->name; ?> .pivot-row-header-total, 
    #<?php echo $this->name; ?> .pivot-data-cell-row-total {
        display: <?php echo $hideSubtotalRow ? 
            'none !important' : 'table-cell'; ?>;
    }

    #<?php echo $this->name; ?> .pivot-column-header-total, 
    #<?php echo $this->name; ?> .pivot-data-cell-column-total,
    #<?php echo $this->name; ?> .pivot-data-header-total {
        display: <?php echo $hideSubtotalColumn ? 
            'none !important' : 'table-cell'; ?>;
    }

</style>
<table id=<?=$uniqueId?> 
    class='pivot-table table table-bordered' style='width:<?= $width ?>; visibility: hidden'>
    <tbody>
        <?php foreach ($colFields as $i => $cf) { 
            $numRF = count($rowFields);
            $numCF = count($colFields);
            $colspan = $numRF;
            $rowspan = $this->showDataHeaders ? $numCF + 1 : $numCF; ?>
            <tr class='pivot-column'>
            <?php if ($i === 0) { ?>
                <td class='pivot-data-field-zone'
                    colspan=<?= $colspan; ?>
                    rowspan=<?= $rowspan; ?>>
                <?php echo implode(' | ', $mappedDataFields); ?>
                </td>
            <?php }
            foreach ($colIndexes as $c => $j) {
                $node = $colNodes[$j];
                $mappedNode = $mappedColNodes[$j];
                $colNodeInfo = $colNodesInfo[$j];
                $colTotalHeader = isset($colNodeInfo[$cf]['total']);
                if (isset($colNodeInfo[$cf]['numChildren'])) { ?>
                    <td class="pivot-column-header 
                        <?php 
                            if ($colTotalHeader) {
                                echo $i === 0 ? 
                                    ' pivot-column-header-grand-total' : 
                                    ' pivot-column-header-total'; 
                            } 
                            echo ' ' . $chClass($cf, $node[$cf]);
                        ?>"
                        data-column-field=<?=$colTotalHeader ? $i-1 : $i?>
                        data-column-index=<?=$c;?>
                        data-num-leaf=<?php 
                            $numLeaf = $colNodeInfo[$cf]['numLeaf'];
                            echo $numLeaf;
                        ?>
                        data-num-children=<?php
                            $numChildren = $colNodeInfo[$cf]['numChildren'];
                            echo $numChildren;
                        ?>
                        data-child-order=<?=$colNodeInfo[$cf]['childOrder']?>
                        colspan=<?php
                            echo $hideSubtotalColumn ? $numLeaf : $numChildren;
                        ?>
                        <?php if ($colTotalHeader)
                            echo "rowspan=".$colNodeInfo[$cf]['level']; ?>
                        data-layer=1
                    >
                        <?php if ($i < count($colFields) - 1 && ! $colTotalHeader) { ?>
                            <i class='fa fa-minus-square-o' aria-hidden='true'></i>
                        <?php } ?>
                        <?= $mappedNode[$cf]; ?>
                    </td>
                <?php }
            } ?>
            </tr>
        <?php } 
        if ($this->showDataHeaders) { ?>
            <tr class='pivot-column'> <?php
            foreach ($colIndexes as $c => $j) {
                $colNodeInfo = $colNodesInfo[$j];
                foreach($dataFields as $df) { ?>
                    <td class='pivot-data-header
                        <?php 
                            if (isset($colNodeInfo['hasTotal']))
                                echo $colNodeInfo['fieldOrder'] === -1 ?
                                    ' pivot-data-header-grand-total' :
                                    ' pivot-data-header-total';  
                        ?>' 
                        data-column-field=<?=$colNodeInfo['fieldOrder']?>
                        data-column-index=<?=$c;?>
                        data-layer=1>
                        <?php 
                            echo $mappedDataFields[$df]; ?>
                    </td>
                    <?php					
                }
            }
        } ?> </tr>
        <?php
        foreach($rowIndexes as $r => $i) {
            $node = $rowNodes[$i];
            $mappedNode = $mappedRowNodes[$i];
            $rowNodeInfo = $rowNodesInfo[$i]; ?>
            <tr class='pivot-row'>
                <?php 
                foreach($rowFields as $j => $rf) {
                    $rowTotalHeader = isset($rowNodeInfo[$rf]['total']);
                    if (isset($rowNodeInfo[$rf]['numChildren'])) { ?>
                        <td class='pivot-row-header 
                            <?php 
                                if ($rowTotalHeader) {
                                    echo $j === 0 ? 
                                        ' pivot-row-header-grand-total' : 
                                        ' pivot-row-header-total'; 
                                }
                                echo ' ' . $rhClass($rf, $node[$rf]);
                            ?>'
                            data-row-field=<?=$rowTotalHeader ? $j-1 : $j?>
                            data-row-index=<?=$r?>
                            data-child-order=<?=$rowNodeInfo[$rf]['childOrder']?> 
                            rowspan=<?= $rowNodeInfo[$rf]['numChildren']; ?> 
                            <?php if ($rowTotalHeader)
                                echo "colspan=".$rowNodeInfo[$rf]['level']; ?> 
                            data-layer=1
                        >
                            <?php if ($j < count($rowFields) - 1 && ! $rowTotalHeader) { ?>
                                <i class='fa fa-minus-square-o' aria-hidden='true'></i>
                            <?php } ?>
                            <?= $mappedNode[$rf]; ?>
                        </td>
                        <?php					
                    }
                }
                foreach ($colIndexes as $c => $j) {
                    $colNodeInfo = $colNodesInfo[$j];
                    $mappedDataRow = Util::get($indexToMappedData, [$i, $j], []);
                    $dataRow = Util::get($indexToData, [$i, $j], []);
                    foreach($dataFields as $di => $df) {
                        $value = Util::get($dataRow, $df, null); ?>
                        <td class='pivot-data-cell  
                            <?php 
                                if (isset($colNodeInfo['hasTotal'])) 
                                    echo $colNodeInfo['fieldOrder'] === -1 ?
                                        ' pivot-data-cell-column-grand-total' :
                                        ' pivot-data-cell-column-total';  
                                if (isset($rowNodeInfo['hasTotal'])) 
                                    echo $rowNodeInfo['fieldOrder'] === -1 ?
                                        ' pivot-data-cell-row-grand-total' :
                                        ' pivot-data-cell-row-total';
                                echo ' ' . $dcClass($df, $value);
                            ?>' 
                            data-data-field=<?=$di?>
                            data-column-field=<?=$colNodeInfo['fieldOrder']?>
                            data-row-field=<?=$rowNodeInfo['fieldOrder']?>
                            data-row-index=<?=$r;?>
                            data-column-index=<?=$c;?>
                            data-layer=1
                        >
                            <?php echo Util::get($mappedDataRow, $df, $emptyValue); ?>
                        </td>
                        <?php					
                    }
                } ?>
            </tr>
        <?php } ?>
    </tbody>
</table>
<script type='text/javascript'>
    KoolReport.widget.init(
        <?php echo json_encode($this->getResources()); ?>,
        function() {
            var rowCollapseLevels = <?php echo json_encode($rowCollapseLevels); ?>;
            rowCollapseLevels.sort(function(a,b){ return b-a;});
            var colCollapseLevels = <?php echo json_encode($colCollapseLevels); ?>;
            colCollapseLevels.sort(function(a,b){ return b-a;});
            var <?=$uniqueId?>_data = {
                id: "<?=$uniqueId?>",
                template: "<?=$this->template?>",
                rowCollapseLevels: rowCollapseLevels,
                colCollapseLevels: colCollapseLevels,
                numRowFields: <?php echo count($rowFields); ?>,
                numColFields: <?php echo count($colFields); ?>,
                numDataFields: <?php echo count($dataFields); ?>,
                hideSubtotalRow: <?php echo $hideSubtotalRow ? 1 : 0; ?>,
                hideSubtotalColumn: <?php echo $hideSubtotalColumn ? 1 : 0; ?>,
                showDataHeaders: <?php echo $this->showDataHeaders ? 1 : 0; ?>,
            };
            <?=$uniqueId?> = KoolReport.PivotTable.create(<?=$uniqueId?>_data);

            <?php $this->clientSideReady("");?>
        }
    );
</script>
