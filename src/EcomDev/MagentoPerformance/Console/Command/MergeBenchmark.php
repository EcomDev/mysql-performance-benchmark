<?php

namespace EcomDev\MagentoPerformance\Console\Command;

use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MergeBenchmark extends Command
{
    /**
     * Configures main command options
     *
     */
    protected function configure()
    {
        $this->setName('merge:benchmark');
        $this->addOption('output-file', 'o', InputOption::VALUE_REQUIRED, 'Output file name for merged benchmark');
        $this->addOption('ignore-sample', 'i', InputOption::VALUE_NONE, 'Ignores sample size');
        $this->addArgument('path', InputArgument::IS_ARRAY|InputArgument::REQUIRED, 'File or directory path with benchmarks');
    }

    /**
     * Merges benchmark
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $files = $this->resolveFiles($input->getArgument('path'));
        $output = Writer::createFromPath($input->getOption('output-file'), 'w');

        if (empty($files)) {
            throw new \InvalidArgumentException('No readable files has been provided');
        }

        $matrix = [];
        $datasets = [];

        foreach ($files as $file) {
            $reader = Reader::createFromPath($file);
            try {
                $data = $reader->fetchAssoc();
            } catch (\Exception $e) {
                continue;
            }

            $dataset = basename($file, '.csv');
            foreach ($data as $row) {
                $path = sprintf('%s-%s', $dataset, $row['sample']);
                if ($input->getOption('ignore-sample')) {
                    $path = $dataset;
                }

                $datasets[$path] = $path;
                $matrix[$row['operation']][$path] = $row['total'];
            }
        }

        $output->insertOne(array_merge(['Operation'], $datasets));

        foreach ($matrix as $code => $row) {
            $output->insertOne(array_merge([$code], array_values($row)));
        }
    }

    private function resolveFiles($files)
    {
        $result = [];
        foreach ($files as $file) {
            if (is_dir($file)) {
                $result = array_merge($result, $this->resolveFiles(
                    iterator_to_array(
                        new \GlobIterator(rtrim($file, '/') . '/*.csv')
                    )
                ));
            } elseif (is_file($file)) {
                $result[] = $file;
            }
        }

        return $result;
    }
}
