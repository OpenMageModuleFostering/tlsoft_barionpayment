<?php
$installer = $this;
$table = $installer->getConnection()
    ->newTable($installer->getTable('tlbarion/transactions'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
        ), 'Entity Id')
	->addColumn('real_orderid', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        ), 'Transaction id')
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
		'unsigned'  => true,
		'nullable' => false,
        ), 'Order Id')
	->addColumn('ccy', Varien_Db_Ddl_Table::TYPE_VARCHAR, 3, array(
        ), 'Order Currency')
	->addColumn('application_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 36, array(
        ), 'Shop ID')
	->addColumn('amount', Varien_Db_Ddl_Table::TYPE_DECIMAL, '12,2', array(
        ), 'Order Amount, without decimal point')
	->addColumn('bariontransactionid', Varien_Db_Ddl_Table::TYPE_VARCHAR, 38, array(
        ), 'ShopTransactionId')
	->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, array(
        'unsigned'  => true,
        ), 'Store Id')
	->addColumn('payment_status', Varien_Db_Ddl_Table::TYPE_VARCHAR, 2, array(
		'nullable' => false,
        ), 'Payment Status')
	->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        ), 'Created At')
	->addForeignKey($installer->getFkName('tlbarion/transactions', 'store_id', 'core/store', 'store_id'),
        'store_id', $installer->getTable('core/store'), 'store_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL, Varien_Db_Ddl_Table::ACTION_CASCADE)
	->addForeignKey($installer->getFkName('tlbarion/transactions', 'order_id', 'sales/order', 'entity_id'),
        'order_id', $installer->getTable('sales/order'), 'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE);
$installer->getConnection()->createTable($table);

$table = $installer->getConnection()
    ->newTable($installer->getTable('tlbarion/transactions_history'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
        ), 'Entity Id')
	->addColumn('transaction_id', Varien_Db_Ddl_Table::TYPE_INTEGER, 10, array(
        'unsigned'  => true,
        'nullable'  => false,
        ), 'Trasnaction id')
	->addColumn('error_message', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
        ), 'Error message')
	->addColumn('error_number', Varien_Db_Ddl_Table::TYPE_INTEGER, 4, array(
        ), 'Error number')
	->addColumn('stornoid', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
		'nullable' => false,
        ), 'Storno ID')
	->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        ), 'Created At')
	->addForeignKey($installer->getFkName('tlbarion/transactions_history', 'transaction_id', 'tlbarion/transactions', 'entity_id'),
        'transaction_id', $installer->getTable('tlbarion/transactions'), 'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE);
$installer->getConnection()->createTable($table);

$installer->endSetup();