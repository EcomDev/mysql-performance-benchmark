<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Flat as FlatOperation;

class Flat
    extends AbstractProvider
{
    protected $batchSize = 20000;

    public function getOperations()
    {
        return [
            'flat_ranged' => function ($scopeId)  {
                return $this->executeOperation(
                    new FlatOperation\Ranged($this, 'entity_flat'),
                    'flat_ranged',
                    $scopeId
                );
            },
            'flat_regular' => function ($scopeId) {
                return $this->executeOperation(
                    new FlatOperation\Regular($this, 'entity_flat'),
                    'flat_regular',
                    $scopeId
                );
            }
        ];
    }

    private function executeOperation(AbstractOperation $operation, $code, $scopeId)
    {
        $this->queryCode = $code;
        $scopeId = $this->scope->getId($scopeId);

        $before = [
            'SET autocommit=0',
            'SET unique_checks=0',
            'SET foreign_key_checks=0',
        ];

        $after = [
            'COMMIT',
            'SET autocommit=1',
            'SET unique_checks=1',
            'SET foreign_key_checks=1'
        ];

        $columns = $this->getConnection()->describeTable($this->getTable('entity_flat'));
        $attributes = $this->attribute->getAll();

        $this->getConnection()->multiQuery(implode('; ', $before));
        $queryTime = $operation($scopeId, $attributes, $columns);
        $this->getConnection()->multiQuery(implode('; ', $after));
        return $queryTime;
    }

    /**
     * Returns a closure that will generate a sample for benchmark
     *
     * @return \Closure
     */
    public function getSampleProvider()
    {
        return function () {
            $this->getConnection()->truncateTable($this->getTable('entity_flat'));
            $sample = [];
            foreach ($this->scope->getCodes() as $code) {
                $sample[] = [$code];
            }

            return $sample;
        };
    }
}
