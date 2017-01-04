<?php
/* create apruvepayment tables */
$installer = $this;

$installer->startSetup();

$connection = $installer->getConnection();
$entity_table = $installer->getTable('apruvepayment/entity');
if(!$connection->isTableExists($entity_table)) {
    $table = $connection->newTable($entity_table)
        ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true
            ), 'ID')
        ->addColumn('magento_id', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
            'nullable'  => false
            ), 'Entity\'s ID in Magento')
        ->addColumn('apruve_id', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
            'nullable'  => false
            ), 'Entity\'s ID in Apruve')
        ->addColumn('apruve_item_id', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false
            ), 'Entity Item ID in Apruve')
        ->addColumn('entity_type', Varien_Db_Ddl_Table::TYPE_TEXT, 10, array(
            'nullable'  => false
            ), 'Entity Type')
        ->addIndex(
            $installer->getIdxName(
                'apruvepayment/entity',
                array(
                    'magento_id',
                    'apruve_id',
                    'entity_type'
                ),
                Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
            ),
            array(
                'magento_id',
                'apruve_id',
                'entity_type'
            ),
            array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE)
        )
        ->addIndex(
            $installer->getIdxName(
                'apruvepayment/entity',
                array(
                    'magento_id',
                    'apruve_id',
                    'entity_type'
                )
            ),
            array(
                'magento_id',
                'apruve_id',
                'entity_type'
            )
        );
    $installer->getConnection()->createTable($table);
}

$installer->endSetup();