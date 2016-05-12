<?php

namespace EcomDev\MagentoPerformance\Console\Command;

class BenchmarkQuery extends AbstractBenchmark
{

    protected function configureName()
    {
        $this->setName('benchmark:query');
        $this->setDescription('Benchmarks different solution for retrieving data from the database by using join vs separate queries.');
    }
}
