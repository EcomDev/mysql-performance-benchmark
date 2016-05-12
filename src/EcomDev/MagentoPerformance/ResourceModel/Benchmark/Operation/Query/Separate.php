<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Query;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractOperation;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractProvider;
use Magento\Framework\DB\Select;

class Separate extends AbstractOperation
{
    /**
     * @var \Closure
     */
    private $filter;

    /**
     * @var array[]
     */
    private $attributeByType;

    public function __construct(AbstractProvider $provider, $attributeByType, \Closure $filter = null)
    {
        parent::__construct($provider);
        $this->filter = $filter;
        $this->attributeByType = $attributeByType;
    }

    protected function execute($args)
    {
        list($offset, $limit) = $args;

        $select = $this->getConnection()->select()->from(
            ['main' => $this->getTable('entity_flat')],
            []
        );

        $select->columns('entity_id', 'main');
        $select->limit($limit, $offset);

        if ($this->filter !== null) {
            $filter = $this->filter;
            $filter($select);
        }

        $rows = [];
        foreach ($this->profiledQuery($select) as $row) {
            $rows[$row['entity_id']] = $row;
        }

        $entityIds = array_keys($rows);

        if (!$entityIds) {
            return ;
        }

        $entityCondition = $this->getConnection()->quoteInto('attribute.entity_id IN(?)', $entityIds);
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
            $attributeTableSelect->where($entityCondition);
            $attributeSelects[] = $attributeTableSelect;
        }

        $select = $this->getConnection()->select();
        $select->union($attributeSelects, Select::SQL_UNION_ALL);
        $select->order('scope_id');

        foreach ($this->profiledQuery($select) as $row) {
            $rows[$row['entity_id']][$selectAttributes[$row['attribute_id']]->code] = $row['value'];
        }
    }
}
