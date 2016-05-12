<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Limit;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractOperation;

class Regular extends AbstractOperation
{
    protected function execute($args)
    {
        list($scopeId, $offset, $limit) = $args;

        $select = $this->getConnection()->select();
        $select->from($this->getTable('entity_flat_data'), '*');
        $select->where('is_active = ?', 1);
        $select->where('scope_id = ?', $scopeId);
        $select->order('firstname');
        $select->limit($limit, $offset);

        $rows = [];
        foreach ($this->profiledQuery($select) as $row) {
            $rows[] = $row;
        }
    }
}
