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

class MemoryTable extends AbstractExport
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
        $this->getConnection()->truncateTable($this->tableName);
        $insertString = sprintf('INSERT INTO %s (entity_id) VALUES ', $this->tableName);
        foreach ($entityIds as $index => $id) {
            $insertString .= ($index > 0 ? ',' : '') . '(' . (int)$id .')';
        }

        $this->profiledQuery($insertString);
    }

}
