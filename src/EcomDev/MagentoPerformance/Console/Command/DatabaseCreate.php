<?php

namespace EcomDev\MagentoPerformance\Console\Command;

use EcomDev\MagentoPerformance\ResourceModel\DatabaseSetup;
use EcomDev\MagentoPerformance\ResourceModel\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseCreate extends Command
{
    /**
     * @var DatabaseSetup
     */
    private $setup;

    /** @var Generator */
    private $generator;

    public function __construct(DatabaseSetup $setup, Generator $generator)
    {
        $this->setup = $setup;
        $this->generator = $generator;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('database:create');
        $this->addArgument('database-size', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Database with particular sample sizes, number in thousands', []);
        $this->addOption('database-prefix', 'p', InputOption::VALUE_OPTIONAL, 'Database name prefix', 'benchmark_database');
        $this->addUsage(
            'database:create 10 100 1000 # will create 3 databases with names: '
            . ' benchmark_database_10, benchmark_database_100, benchmark_database_1000. '
            . ' Each number is multiplied by 1000, to produce number of test rows'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $databasePrefix = $input->getOption('database-prefix');
        $sizes = $input->getArgument('database-size');
        if (empty($sizes)) {
            $output->writeln('<error>At least one database size needs to be specified</error>');
            return 1;
        }

        foreach ($sizes as $size) {
            if (!is_numeric($size)) {
                $output->writeln(sprintf('<error>Size is a numeric value: %s</error>', $size));
                return 1;
            }
        }

        foreach ($sizes as $size) {
            $dbName = sprintf('%s_%s', $databasePrefix, (int)$size);
            $this->setup->createSchema($dbName);
            $this->generator->generate($dbName, ((int)$size)*1000);
        }

        return 0;
    }


}
