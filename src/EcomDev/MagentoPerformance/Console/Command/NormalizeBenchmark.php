<?php

namespace EcomDev\MagentoPerformance\Console\Command;

use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class NormalizeBenchmark extends Command
{
    /**
     * Configures main command options
     *
     */
    protected function configure()
    {
        $this->setName('normalize:benchmark');
        $this->addOption('output-path', 'o', InputOption::VALUE_REQUIRED, 'Output file name for merged benchmark');
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

        if (empty($files)) {
            throw new \InvalidArgumentException('No readable files has been provided');
        }

        foreach ($files as $file) {
            $reader = Reader::createFromPath($file);
            $basename = basename($file);
            $output = Writer::createFromPath(rtrim($input->getOption('output-path'), '/') . $basename);
            $matrix = [];
            $samples = [];
            foreach ($reader->fetchAssoc() as $row) {
                $samples[$row['sample']] = true;
                $matrix[$row['operation']][$row['sample']] = $row['total'];
            }

            $output->insertOne(array_merge(['Operation'], array_keys($samples)));

            foreach ($matrix as $code => $row) {
                $output->insertOne(array_merge([$code], array_values($row)));
            }
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
