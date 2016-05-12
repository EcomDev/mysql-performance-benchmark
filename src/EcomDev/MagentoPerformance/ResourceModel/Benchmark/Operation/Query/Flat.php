<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Query;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractOperation;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractProvider;

class Flat extends AbstractOperation
{
    /**
     * @var \Closure
     */
    private $filter;

    public function __construct(AbstractProvider $provider, \Closure $filter = null)
    {
        parent::__construct($provider);
        $this->filter = $filter;
    }

    protected function execute($args)
    {
        list($offset, $limit) = $args;

        $select = $this->select()->from(
            $this->getTable('entity_flat_data'),
            array_merge(['entity_id'], $this->provider->getAttributeCodes())
        );

        $select->limit($limit, $offset);

        if ($this->filter !== null) {
            $filter = $this->filter;
            $filter($select);
        }

        $rows = [];
        foreach ($this->profiledQuery($select) as $row) {
            $rows[$row['entity_id']] = $row;
        }
    }
}
