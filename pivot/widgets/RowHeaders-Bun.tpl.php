<div class='krpmRowHeaderZoneDiv'>
    <table class='table'>
        <tbody>
            <?php 
            foreach($rowIndexes2 as $r => $i) {
                $node = $rowNodes[$i];
                $mappedNode = $mappedRowNodes2[$i];
                $rowNodeInfo = $rowNodesInfo2[$i]; ?>
                <tr class='krpmRow'>
                    <?php foreach ($rowFields as $j => $rf) {
                        $rowTotalHeader = isset($rowNodeInfo[$rf]['total']);
                        $subTotalHeader = $rowTotalHeader && $j > 0;
                        if (isset($rowNodeInfo[$rf]['numChildren']) && 
                            ! $subTotalHeader) { ?>
                            <td class='krpmRowHeader
                                <?php
                                    if (isset($rowNodeInfo['hasTotal'])) {
                                        echo $rowNodeInfo['fieldOrder'] === -1 ? 
                                            ' krpmRowHeaderGrandTotal' : 
                                            ' krpmRowHeaderTotal';
                                    }
                                    echo ' ' . $rhClass($cf, $node[$rf]);
                                ?>'
                                data-row-field=<?= $rowTotalHeader ? $j-1 : $j?>
                                data-row-index=<?=$r?>
                                data-node='<?= htmlspecialchars($node[$rf], ENT_QUOTES) ?>'
                                data-num-children=<?= $rowNodeInfo[$rf]['numChildren']; ?> 
                                data-row-layer=1
                                data-column-layer=1
                                data-page-layer=1
                                style='display:' 
                            >
                                <?php for ($indent=0; $indent<$j; $indent++)
                                    echo "<span class='krpm-indent'>&nbsp</span>"; ?>
                                <?php if ($j < count($rowFields) - 1 && 
                                    ! $rowTotalHeader) { ?>
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