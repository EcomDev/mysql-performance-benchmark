<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Query;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractOperation;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractProvider;

class JoinFlat extends AbstractOperation
{
    /**
     * @var \Closure
     */
    private $filter;

    /**
     * @var \stdClass[]
     */
    private $attributes;

    public function __construct(AbstractProvider $provider, $attributes, \Closure $filter = null)
    {
        parent::__construct($provider);
        $this->filter = $filter;
        $this->attributes = $attributes;
    }

    protected function execute($args)
    {
        list($offset, $limit) = $args;
        $select = $this->getConnection()->select()->from(
            ['main' => $this->getTable('entity_flat')],
            []
        );

        $select->columns('entity_id', 'main');

        if ($this->filter !== null) {
            $filter = $this->filter;
            $filter($select);
        }

        foreach ($this->provider->getAttributeCodes() as $code) {
            if (!isset($this->attributes[$code])) {
                continue;
            }

            $this->provider->joinAttribute($select, $this->attributes[$code], 'main');
        }

        $select->limit($limit, $offset);

        $rows = [];
        foreach ($this->profiledQuery($select) as $row) {
            $rows[] = $row;
        }
    }
}
