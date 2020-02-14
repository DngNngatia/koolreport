<div class='krpmRowHeaderZoneDiv'>
    <table class='table'>
        <tbody>
            <?php 
            foreach($rowIndexes as $r => $i) {
                $mappedNode = $mappedRowNodes[$i];
                $node = $rowNodes[$i];
                $rowNodeInfo = $rowNodesInfo[$i]; ?>
                <tr class='krpmRow'>
                    <?php foreach ($rowFields as $j => $rf) {
                        $rowTotalHeader = isset($rowNodeInfo[$rf]['total']);
                        if (isset($rowNodeInfo[$rf]['numChildren'])) { ?>
                            <td class='krpmRowHeader
                                <?php
                                    if ($rowTotalHeader) {
                                        echo $j === 0 ?
                                            ' krpmRowHeaderGrandTotal' :
                                            ' krpmRowHeaderTotal';
                                    }
                                    echo ' ' . $rhClass($cf, $node[$rf]);
                                ?>'
                                data-row-field=<?= $rowTotalHeader ? $j-1 : $j?>
                                data-row-index=<?=$r?>
                                data-node = '<?= htmlspecialchars($node[$rf], ENT_QUOTES) ?>'
                                rowspan=<?= $rowNodeInfo[$rf]['numChildren']; ?> 
                                <?php if ($rowTotalHeader)
                                    echo "colspan=".$rowNodeInfo[$rf]['level']; ?>
                                data-row-layer=1
                                data-column-layer=1
                                data-page-layer=1
                                style='display:' 
                            >
                                <?php if ($j < count($rowFields) - 1 
                                    && ! $rowTotalHeader)  { ?>
                                    <i class='fa fa-minus-square-o' aria-hidden='true'></i>
                                <?php } ?>
                                    <span class='krpmHeaderText'>
                                        <?= $mappedNode[$rf]; ?>
                                    </span>
                            </td>
                        <?php }   
                    } ?>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>