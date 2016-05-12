<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Limit;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractOperation;

class Separate extends AbstractOperation
{
    protected function execute($args)
    {
        list($scopeId, $offset, $limit) = $args;

        $idSelect = $this->getConnection()->select();
        $idSelect->from($this->getTable('entity_flat_data'), ['entity_id']);
        $idSelect->where('scope_id = ?', $scopeId);
        $idSelect->where('is_active = ?', 1);
        $idSelect->order('firstname');
        $idSelect->limit($limit, $offset);

        $rows = [];
        foreach ($this->profiledQuery($idSelect) as $row) {
            $rows[$row['entity_id']] = $row;
        }

        $select = $this->getConnection()->select();
        $select->from($this->getTable('entity_flat_data'), '*');
        $select->where('scope_id = ?', $scopeId);
        $select->where('entity_id IN(?)', array_keys($rows));

        foreach ($this->profiledQuery($select) as $row) {
            $rows[$row['entity_id']] += $row;
        }
    }
}
