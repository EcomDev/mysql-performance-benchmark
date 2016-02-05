<?php

namespace EcomDev\MagentoPerformance\Console\Command;


class BenchmarkLimit  extends AbstractBenchmark
{
    protected function configureName()
    {
        $this->setName('benchmark:limit');
        $this->setDescription('Benchmarks different options for limit calculation.');
    }
}
