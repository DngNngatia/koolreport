<div class='krpmColumnHeaderZoneDiv'>
    <table class='table'>
        <tbody>
            <?php 
            foreach ($colFields as $i => $cf) { ?>
                <tr>
                <?php foreach ($colIndexes as $c => $j) {
                    $node = $colNodes[$j];
                    $mappedNode = $mappedColNodes[$j];
                    $colNodeInfo = $colNodesInfo[$j];
                    $colTotalHeader = isset($colNodeInfo[$cf]['total']);
                    if (isset($colNodeInfo[$cf]['numChildren'])) { ?>
                        <td class='krpmColumnHeader
                            <?php
                                if ($colTotalHeader) {
                                    echo $i === 0 ?
                                        ' krpmColumnHeaderGrandTotal' : 
                                        ' krpmColumnHeaderTotal';
                                }
                                echo ' ' . $chClass($cf, $node[$cf]);
                            ?>'
                            data-column-field=<?=$colTotalHeader ? $i-1 : $i?>
                            data-column-index=<?=$c;?>
                            data-column-layer=1
                            data-row-layer=1
                            data-page-layer=1
                            data-num-leaf=<?php 
                                $numLeaf = $colNodeInfo[$cf]['numLeaf'];
                                echo $numLeaf;
                            ?>
                            data-num-children=<?php 
                                $numChildren = $colNodeInfo[$cf]['numChildren'];
                                echo $numChildren;
                            ?>
                            data-node = '<?= htmlspecialchars($node[$cf], ENT_QUOTES) ?>'
                            colspan=<?= $hideSubtotalColumn ? $numLeaf : $numChildren; ?>
                            rowspan=<?= $colTotalHeader ? $colNodeInfo[$cf]['level'] : 1 ?>
                            style='display:' 
                        >
                            <?php if ($i < count($colFields) - 1 && ! $colTotalHeader) { ?>
                                <i class='fa fa-minus-square-o' aria-hidden='true'></i>
                            <?php } ?>
                            <span class='krpmHeaderText'>
                                <?= $mappedNode[$cf]; ?>
                            </span>
                        </td>
                    <?php } ?>
                <?php } ?>
                </tr>
            <?php } ?>
            <?php if ($this->showDataHeaders) { ?>
                <tr class='krpmDataHeaderRow'>
                <?php foreach ($colIndexes as $c => $j) {
                    $colNodeInfo = $colNodesInfo[$j];
                    foreach($dataFields as $df) { ?>
                        <td class='krpmDataHeader 
                            <?php
                                if (isset($colNodeInfo['hasTotal'])) 
                                    echo $colNodeInfo['fieldOrder'] === -1 ?
                                        ' krpmDataHeaderColumnGrandTotal' :
                                        ' krpmDataHeaderColumnTotal';  
                            ?>' 
                            data-column-field=<?=$colNodeInfo['fieldOrder']?>
                            data-column-index=<?=$c;?>
                            data-column-layer=1
                            data-row-layer=1
                            data-page-layer=1 
                        >
                            <span class='krpmDataHeaderText'>
                            <?php 
                                echo $mappedDataFields[$df]; ?>
                            </span>
                        </td>
                    <?php } ?>
                <?php } ?>
                </tr>
            <?php } ?>
        </tbody>
        <colgroup>
            <?php 
                foreach ($colIndexes as $c => $j) { 
                    $colNodeInfo = $colNodesInfo[$j];
                    $colClass = '';
                    if (isset($colNodeInfo['hasTotal'])) 
                        $colClass = $colNodeInfo['fieldOrder'] === -1 ?
                            ' krpmColumnHeaderColGrandTotal' :
                            ' krpmColumnHeaderColTotal'; 

                    foreach ($dataFields as $df) 
                        echo "<col class='$colClass'>";
                } 
            ?>
        </colgroup>
    </table>
</div>
