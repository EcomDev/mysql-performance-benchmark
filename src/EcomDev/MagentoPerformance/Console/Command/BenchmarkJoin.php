<?php

namespace EcomDev\MagentoPerformance\Console\Command;

class BenchmarkJoin extends AbstractBenchmark
{

    protected function configureName()
    {
        $this->setName('benchmark:join');
        $this->setDescription('Benchmarks different solution for retrieving data from the database by using join.');
    }
}
