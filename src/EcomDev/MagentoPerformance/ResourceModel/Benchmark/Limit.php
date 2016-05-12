<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Limit as LimitOperation;
use Magento\Framework\DB\Select;

class Limit
    extends AbstractProvider
{

    public function getOperations()
    {
        return [
            'single' => new LimitOperation\Regular($this),
            'separate' => new LimitOperation\Ranged($this)
        ];
    }

    protected function configureBoundarySelect(Select $select)
    {
        $this->limitFlatActive($select);
        return parent::configureBoundarySelect($select);
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

            if ($runCount * $size > $maximumBoundary) {
                $runCount = floor($maximumBoundary / $size);
            }

            $factor = 1;
            $scopeId = $this->scope->getId($this->scopeCode);

            while ($runCount > $step) {
                $factor *= -1;
                $offsetLocation = $size * $step;

                if ($factor < 0) {
                    $offsetLocation = max($maximumBoundary - $offsetLocation - $size, 0);
                }

                $samples[] = [$scopeId, $offsetLocation, $size];
                $step ++;
            }

            return $samples;
        };
    }
}
