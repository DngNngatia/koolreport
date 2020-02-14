# Change Log

## Version 2.0.0

1. DataTables: Support bootstrap 4


## Version 1.5.0

1. Update footer formatValue
2. Use Utility::jsonEncode() to enable writing anonymous js function in options
3. Add data-order and data-search to DataTables' columns setting like this:
    'columns' => [
        'customerName' => [
            'data-order' => 'customerNumber',
            'data-search' => 'customerFullName',
        ],
    ]
    
## Version 1.2.0

1. DataTables: Add: cssClass option for table, th, tr, td, tf.
2. DataTables: Fix: tfooter -> tfoot.
3. DataTables: Fix: clientEvents like "select" not run.


## Version 1.1.0

1. DataTables:Remove dataStore and use the default dataSource/dataStore from Widget
2. DataTables: Adding formatValue capability
3. Improve the client library loading.

## Version 1.0.0

1. Adding `DataTables` widget.