<?php

namespace EcomDev\MagentoPerformance\Console\Command;

class BenchmarkFlatData extends AbstractBenchmark
{
    protected function configureName()
    {
        $this->setName('benchmark:flat:data');
        $this->setDescription('Benchmarks flat index buidling');
    }
}
