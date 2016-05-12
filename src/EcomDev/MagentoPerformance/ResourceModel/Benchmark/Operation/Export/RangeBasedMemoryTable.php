<?php
/**
 * Created by PhpStorm.
 * User: ivan
 * Date: 12/05/16
 * Time: 09:52
 */

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Export;


use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Select;

class RangeBasedMemoryTable extends AbstractExport
{
    private $tableName;

    protected function init()
    {
        $this->tableName = $this->createMemoryTable();
    }


    protected function applyCondition(Select $select)
    {
        $select->join(['id' => $this->tableName], 'attribute.entity_id = id.entity_id', []);
    }

    protected function prepareCondition($entityIds)
    {
        $minId = reset($entityIds);
        $maxId = end($entityIds);

        $this->getConnection()->truncateTable($this->tableName);
        $idSelect = $this->provider->getMainSelect('main')
            ->columns('main.entity_id')
            ->where('main.entity_id >= ?', $minId)
            ->where('main.entity_id <= ?', $maxId);

        $this->profiledQuery(
            $this->getConnection()->insertFromSelect($idSelect, $this->tableName, ['entity_id'])
        );
    }

}
