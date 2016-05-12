<?php

namespace EcomDev\MagentoPerformance\Console\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BenchmarkExport extends AbstractBenchmark
{
    protected function configureName()
    {
        $this->setName('benchmark:export');
        $this->setDescription('Benchmarks different solution for in conditions in MySQL for exporting large amount of data');
        $this->addOption('random', 'r', InputOption::VALUE_NONE, 'Should be used random processing');
    }

    protected function configureProvider(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('random')) {
            $this->provider->setOption('random', true);
        }
    }


}
