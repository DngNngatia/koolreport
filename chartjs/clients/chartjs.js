if(typeof ChartJS =="undefined")
{
    function ChartJS(name,settings)
    {
        var ctx = document.getElementById(name).getContext("2d");
        
        if(settings.type=="scatter")
        {
            settings.options.tooltips = settings.options.tooltips || {};
            settings.options.tooltips.callbacks = {
                label:function(item,data){
                    var piece = data.datasets[item.datasetIndex].data[item.index];
                    return "(x,y)".replace("x",piece.x).replace("y",piece.y);
                }
            };
        }
        else if(settings.type=="bubble")
        {
            settings.options.tooltips = settings.options.tooltips || {};
            settings.options.tooltips.callbacks = {
                label:function(item,data){
                    var piece = data.datasets[item.datasetIndex].data[item.index];
                    return "(x,y,v)".replace("x",piece.x).replace("y",piece.y).replace("v",piece.v);
                }
            };
        }
        else
        {
            settings.options.tooltips = settings.options.tooltips || {};
            settings.options.tooltips.callbacks = {
                label:function(item,data){
                    return data.datasets[item.datasetIndex].label+" : " + data.datasets[item.datasetIndex].fdata[item.index];
                },
            };
        }
        settings.options.onClick = function(e,items)
        {
            for(var i in items)
            {
                var index = items[i]._index;
                var datasetIndex = items[i]._datasetIndex;
                var totalDataset = items[i]._chart.config.data.datasets.length;
                var selectedLabel = items[i]._chart.config.data.labels[index];
                var selectedRow = [selectedLabel];
                for(var j=0;j<totalDataset;j++)
                {
                    selectedRow.push(items[i]._chart.config.data.datasets[j].data[index]);
                }
                this.cKeys.forEach(function(cKey,i){
                    if(typeof selectedRow[i]!= "undefined")
                    {
                        selectedRow[cKey] = selectedRow[i];
                    }
                });
                this.fireEvent("itemSelect",{
                    selectedValue:items[i]._chart.config.data.datasets[datasetIndex].data[index],
                    selectedRow:selectedRow,
                    selectedLabel:selectedLabel,
                    selectedRowIndex:index,
                    selectedColumnIndex:datasetIndex+1,
                });
            }
        }.bind(this);
    
    
        if(settings.type=="polar")
        {
            this.chart = new Chart.PolarArea(ctx,settings);
        }
        else if(settings.type=="scatter")
        {
            settings.type="bubble";
            this.chart = new Chart(ctx,settings);
        }
        else
        {
            if(settings.type=="bubble")
            {
                settings.options.elements = settings.options.elements||{};
                settings.options.elements.point = settings.options.elements.point||{};
                settings.options.elements.point.radius = function(context)
                {
                    var value = context.dataset.data[context.dataIndex];
                    var size = context.chart.width;
                    var base = Math.abs(value.v) * value.s/100;
                    return (size / 24) * base;
                }
            }
            
            this.chart = new Chart(ctx,settings);
        }
        this.events = {};
        this.cKeys = settings.cKeys;
    }
    ChartJS.prototype = {
        chart:null,
        events:null,
        cKeys:null,
        on:function(name,handler)
        {
            if(typeof this.events[name]=='undefined')
            {
                this.events[name] = [];
            }
            this.events[name].push(handler);
        },
        fireEvent:function(name,params)
        {
            if(typeof this.events[name]!='undefined')
            {
                for(var i in this.events[name])
                {
                    this.events[name][i](params);
                }
            }
        }
    }
}