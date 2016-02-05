<?php

namespace EcomDev\MagentoPerformance\Console\Command;

class BenchmarkFlat extends AbstractBenchmark
{
    protected function configureName()
    {
        $this->setName('benchmark:flat');
        $this->setDescription('Benchmarks flat index buidling');
    }
}
