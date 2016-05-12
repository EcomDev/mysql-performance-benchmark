<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

use Diff\Differ\MapDiffer;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Select;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Export as ExportOperation;
use Zend\Soap\Exception\RuntimeException;

class Export
    extends AbstractProvider
{

    public function getOperations()
    {
        $operations = [
            'in' => new ExportOperation\In($this, $this->attribute->getAllByType()),
            'memory_join' => new ExportOperation\MemoryTable($this, $this->attribute->getAllByType()),
            'optimized_in' => new ExportOperation\OptimizedIn($this, $this->attribute->getAllByType()),
            'range_memory_join' => new ExportOperation\RangeBasedMemoryTable($this, $this->attribute->getAllByType()),
        ];

        if ($this->getOption('random', false)) {
            unset($operations['range_memory_join']);
        }

        return $operations;
    }

    /**
     * Returns a closure that will generate a sample for benchmark
     *
     * @return \Closure
     */
    public function getSampleProvider()
    {
        return function ($size, $maximumBoundary, $runCount) {
            $step = 0;
            $samples = [];

            if (($runCount * $size) > $maximumBoundary) {
                $runCount = floor($maximumBoundary / $size);
            }

            $idSelect = $this->getMainSelect('main')
                ->columns('main.entity_id');

            if ($this->getOption('random')) {
                $idSelect->order('RAND()');
            } else {
                $idSelect->order('main.entity_id');
            }

            while ($runCount > $step && $maximumBoundary > ($size * $step)) {
                $offsetLocation = $size * $step;
                $idSelect->reset(Select::LIMIT_COUNT);

                if ($this->getOption('random')) {
                    $idSelect->limit($size);
                } else {
                    $idSelect->reset(Select::LIMIT_OFFSET);
                    $idSelect->limit($size, $offsetLocation);
                }

                $ids = $this->getConnection()->fetchCol($idSelect);
                $samples[] = [$ids];
                $step ++;
            }

            return $samples;
        };
    }
}
