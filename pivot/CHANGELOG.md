# Change Log

## Version 4.2.0

1. Add 'hideSubtotalRow' and 'hideSubtotalColumn' to PivotTable and PivotMatrix widgets.

## Version 4.1.0

1. Add 'sum percent' and 'count percent' to Pivot process.


## Version 4.0.0

1. PivotMatrix: Fix: getTotalOffset in PivotMatrix.js. 
2. PivotMatrix: Fix: escape quote in header's dataset.node and in json_encode($config). 
3. PivotMatrix: Add: column header, row header and data cell Total css classes. 
4. PivotMatrix: Add: dataset row-field and column-field for data cells. 
5. PivotMatrix: Add: event 'afterFieldMove' AFTER each field move update. 
6. PivotMatrix: Add: expandUptoLevel function. 
7. PivotMatrix: Add: krpmRowHeaderTotal, krpmColumnHeaderTotal, krpmDataCellRowTotal, krpmDataCellColumnTotal, krmpDataCellRowTotalTr class to to help hide subtotal row.  
8. Pivot: Add: command "expand" => level. 


## Version 3.3.0

1. Bug fix: Move field to empty row or column zones.
2. Feature: Add PivotExtract process to extract tabular data from pivot data.

## Version 3.2.0

1. Minor javascript bug fixes.
2. Add property "partialProcessing" for Pivot process to increase speed.
3. Add property 'columnWidth' for PivotMatrix widget.
 
## Version 3.0.1

1. Fix the average calculation in Pivot    

## Version 3.0.0

1. Add PivotMatrix widget for dragging and dropping fields, sorting, paging, scrolling. 
2. Incremental processing: only compute necessary pivot data at the visible level. Compute more when users click expand/collapse.