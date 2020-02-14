var KoolReport = KoolReport || {};

KoolReport.PivotTable = KoolReport.PivotTable || (function () {

    function changeLayer(el, expand) {
        el.dataset.layer -= expand ? -1 : 1;
        if (expand && 1 * el.dataset.layer > 0)
            el.style.display = '';
        else if (!expand && 1 * el.dataset.layer < 1)
            el.style.display = 'none';
    }
    
    function expandCollapseRow(e) {
        var icon = e.currentTarget;
        var iconName = icon.className;
        var expand = iconName.indexOf('plus') > -1;
        var td = icon.parentElement;
        var el = td.nextElementSibling;
        while (el) {
            this.changeLayer(el, expand);
            el = el.nextElementSibling;
        }
        el = td.parentElement;
        var i = 1;
        while (i < 1 * td.rowSpan) {
            el = el.nextElementSibling;
            for (var j = 0; j < el.children.length; j += 1) {
                var child = el.children[j];
                if (child.classList.contains('pivot-data-cell') &&
                    i === td.rowSpan - 1) {
                    child.classList.toggle('pivot-data-cell-row-total');
                    continue;
                }
                this.changeLayer(child, expand);
            }
            i += 1;
        }
        td.colSpan = expand ? 1 : this.numRowFields - td.dataset.rowField;
        icon.className = expand ? iconName.replace('plus', 'minus') :
            iconName.replace('minus', 'plus');
    }
    
    function expandCollapseRowBun(e) {
        var icon = e.currentTarget;
        var iconName = icon.className;
        var expand = iconName.indexOf('plus') > -1;
        var td = icon.parentElement;
        var el = td.nextElementSibling;
        while (el) {
            el.classList.toggle('pivot-data-cell-row-total');
            el = el.nextElementSibling;
        }
        td.classList.toggle('pivot-row-header-total', expand);
        tr = td.parentElement;
        var i = 1;
        while (i < 1*td.dataset.numChildren) {
            tr = tr.nextElementSibling;
            for (var j = 0; j<tr.children.length; j+=1) {
                var child = tr.children[j];
                this.changeLayer(child, expand);
            }
            i += 1;
        }
        icon.className = expand ? iconName.replace('plus', 'minus') : 
            iconName.replace('minus', 'plus');
    }
    
    function expandCollapseColumn(e) {
        var icon = e.currentTarget;
        var iconName = icon.className;
        var expand = iconName.indexOf('plus') > -1;
        var td = icon.parentElement;
        var rangeLeft = 1 * td.dataset.columnIndex;
        var numChildren = 1 * td.dataset.numChildren;
        var rangeRight = rangeLeft + numChildren / this.numDataFields;
    
        // var numDf = this.numDataFields;
        // var colspan = this.hideSubtotalColumn ?
        //     numChildren - numDf : numChildren;
        // td.colSpan = expand ? colspan : numDf;
    
        var el = td.parentElement;
        el = el.nextElementSibling;
        while (el) {
            var children = el.children;
            for (var i = 0; i < children.length; i += 1) {
                var child = children[i];
                var columnIndex = 1 * child.dataset.columnIndex;
                if (child.classList.contains('pivot-data-header') &&
                    columnIndex === rangeRight - 1) {
                    child.classList.toggle('pivot-data-header-total');
                    child.colSpan = expand ? 1 : td.colSpan/this.numDataFields;
                } else if (child.classList.contains('pivot-data-cell') &&
                    columnIndex === rangeRight - 1) {
                    child.classList.toggle('pivot-data-cell-column-total');
                    child.colSpan = expand ? 1 : td.colSpan/this.numDataFields;
                } else if (rangeLeft <= columnIndex && columnIndex < rangeRight)
                    this.changeLayer(child, expand);
            }
            el = el.nextElementSibling;
        }
        td.rowSpan = expand ? 1 : this.numColFields - td.dataset.columnField;
        icon.className = expand ? iconName.replace('plus', 'minus') :
            iconName.replace('minus', 'plus');
    }
    
    function showHideColumn(td, show) {
        var rangeLeft = 1 * td.dataset.columnIndex;
        var rangeRight = rangeLeft + 1 * td.colSpan / this.numDataFields;
        var el = td.parentElement;
        el = el.nextElementSibling;
        while (el) {
            var children = el.children;
            for (var i = 0; i < children.length; i += 1) {
                var child = children[i];
                var columnIndex = 1 * child.dataset.columnIndex;
                if (rangeLeft <= columnIndex && columnIndex < rangeRight)
                    this.changeLayer(child, show);
            }
            el = el.nextElementSibling;
        }
        this.changeLayer(td, show);
    }

    function create(piTbl_data) {
        var piTbl = piTbl_data;
        piTbl.init = init;
        piTbl.changeLayer = changeLayer;
        piTbl.expandCollapseRow = expandCollapseRow;
        piTbl.expandCollapseRowBun = expandCollapseRowBun;
        piTbl.expandCollapseColumn = expandCollapseColumn;
        piTbl.showHideColumn = showHideColumn;
        piTbl.init();
        return piTbl;
    }

    function init() {
        var pivot = document.getElementById(this.id);

        var tds = pivot.getElementsByClassName('pivot-row-header');
        var expandCollapseFunc = 'expandCollapseRow';
        if (this.template === 'PivotTable-Bun') {
            expandCollapseFunc = 'expandCollapseRowBun';
        }
        for (var i=0; i<tds.length; i+=1) {
            var icon = tds[i].querySelector('i.fa');
            if (! icon) continue;
            icon.addEventListener('click', this[expandCollapseFunc].bind(this));
        }
        for (var j = 0; j < this.rowCollapseLevels.length; j += 1) {
            var rowLevel = this.rowCollapseLevels[j];
            for (var i = 0; i < tds.length; i += 1) {
                var td = tds[i];
                if (td.dataset.rowField != rowLevel)
                    continue;
                var icon = td.querySelector('i.fa');
                if (icon && icon.className.indexOf('minus') > -1)
                    icon.click();
            }
        }

        tds = pivot.getElementsByClassName('pivot-column-header');
        var expandCollapseFunc = 'expandCollapseColumn';
        for (var j=0; j<tds.length; j+=1) {
            var icon = tds[j].querySelector('i.fa');
            if (! icon) continue;
            icon.addEventListener('click', this[expandCollapseFunc].bind(this));
        }
        for (var j = 0; j < this.colCollapseLevels.length; j += 1) {
            var colLevel = this.colCollapseLevels[j];
            for (var i = 0; i < tds.length; i += 1) {
                var td = tds[i];
                if (td.dataset.columnField != colLevel)
                    continue;
                var icon = td.firstElementChild;
                if (icon && icon.className.indexOf('minus') > -1)
                    icon.click();
            }
        }

        pivot.style.visibility = 'visible';
    }

    return {
        create: create,
    };

})();

