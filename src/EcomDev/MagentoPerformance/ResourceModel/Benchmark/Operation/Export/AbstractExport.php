<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Export;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractOperation;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractProvider;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Select;

abstract class AbstractExport extends AbstractOperation
{
    /**
     * @var array[]
     */
    private $attributeByType;

    /**
     * @var Table
     */
    private $memoryTable;

    public function __construct(AbstractProvider $provider, array $attributeByType)
    {
        parent::__construct($provider);
        $this->attributeByType = $attributeByType;
        $this->init();
    }

    protected function init()
    {
        return $this;
    }

    protected function execute($args)
    {
        list($entityIds) = $args;
        $this->prepareCondition($entityIds);
        $rows = [];
        foreach ($entityIds as $id) {
            $rows[$id] = ['entity_id' => $id];
        }

        $attributeCodes = $this->provider->getAttributeCodes();
        $attributeSelects = [];
        $selectAttributes = [];
        foreach ($this->attributeByType as $type => $attributes) {
            $attributeIds = [];
            foreach ($attributes as $id => $attribute) {
                if (!isset($attributeCodes[$attribute->code])) {
                    continue;
                }
                $attributeIds[] = $id;
                $selectAttributes[$id] = $attribute;
            }

            if (empty($attributeIds)) {
                continue;
            }

            $attributeTableSelect  = $this->provider->getAttributeSelect($type, 'attribute', $attributeIds);
            $this->applyCondition($attributeTableSelect);
            $attributeSelects[] = $attributeTableSelect;
        }

        $select = $this->getConnection()->select();
        $select->union($attributeSelects, Select::SQL_UNION_ALL);
        $select->order('scope_id');

        foreach ($this->profiledQuery($select) as $row) {
            $rows[$row['entity_id']][$selectAttributes[$row['attribute_id']]->code] = $row['value'];
        }
    }

    abstract protected function applyCondition(Select $select);
    abstract protected function prepareCondition($entityIds);

    protected function createMemoryTable()
    {
        $this->memoryTable = $this->createTable([[
            'COLUMN_NAME'      => 'entity_id',
            'DATA_TYPE'        => 'int',
            'DEFAULT'          => null,
            'NULLABLE'         => false,
            'LENGTH'           => null,
            'SCALE'            => null,
            'PRECISION'        => null,
            'UNSIGNED'         => true,
            'PRIMARY'          => true,
            'PRIMARY_POSITION' => 0,
            'IDENTITY'         => false
        ]]);

        return $this->memoryTable->getName();
    }

    public function __destruct()
    {
        if ($this->memoryTable) {
            $this->dropMemoryTable();
        }
    }


    private function dropMemoryTable()
    {
        $this->dropTable($this->memoryTable);
        $this->memoryTable = null;
        return $this;
    }
}
