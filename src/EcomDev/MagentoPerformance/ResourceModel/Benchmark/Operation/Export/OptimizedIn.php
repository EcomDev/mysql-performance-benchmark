<?php
/**
 * Created by PhpStorm.
 * User: ivan
 * Date: 12/05/16
 * Time: 09:52
 */

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Export;


use Magento\Framework\DB\Select;

class OptimizedIn extends AbstractExport
{
    private $entityCondition;

    protected function applyCondition(Select $select)
    {
        $select->where($this->entityCondition);
    }

    protected function prepareCondition($entityIds)
    {
        $this->entityCondition = $this->getConnection()->quoteInto('attribute.entity_id IN(?)', $entityIds);
    }

}
