<?php
/**
 * Created by PhpStorm.
 * User: ivan
 * Date: 12/05/16
 * Time: 09:52
 */

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Export;


use Magento\Framework\DB\Select;

class In extends AbstractExport
{
    private $entityIds;

    protected function applyCondition(Select $select)
    {
        $select->where('attribute.entity_id IN(?)', $this->entityIds);
    }

    protected function prepareCondition($entityIds)
    {
        $this->entityIds = $entityIds;
    }

}
