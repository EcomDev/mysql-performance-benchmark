<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

use Magento\Framework\DB\Select;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Query as QueryOperation;

class Query
    extends AbstractProvider
{

    public function getOperations()
    {

        $joinFilter = function (Select $select) {
            $valueExpression = $this->joinAttribute($select, $this->attribute->getAll()['is_active'], 'main');
            $select->where(sprintf('%s = ?', $valueExpression), 1);
        };

        $flatFilter = function (Select $select) {
            $this->limitFlatActive($select);
        };

        $directFilter = function (Select $select) {
            $select->where('scope_id = ?', $this->scope->getId($this->scopeCode));
            $select->where('is_active = ?', 1);
        };

        return [
            'single_join_query_join_filter' => new QueryOperation\Join($this, $this->attribute->getAll(), $joinFilter),
            'single_join_query_flat_filter' => new QueryOperation\Join($this, $this->attribute->getAll(), $flatFilter),
            'single_flat_query_direct_filter' => new QueryOperation\Flat($this, $directFilter),
            'separate_eav_data_query_direct_flat_filter' => new QueryOperation\Separate($this, $this->attribute->getAllByType(), $directFilter),
        ];
    }

    /**
     * Returns a closure that will generate a sample for benchmark
     *
     * @return \Closure
     */
    public function getSampleProvider()
    {
        return $this->getLimitSample();
    }
}
