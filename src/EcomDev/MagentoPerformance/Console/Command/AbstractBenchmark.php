<?php

namespace EcomDev\MagentoPerformance\Console\Command;

use EcomDev\MagentoPerformance\Model\Benchmark;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\ProviderInterface;
use League\Csv\Writer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\ServiceManager\Exception\RuntimeException;

abstract class AbstractBenchmark extends Command
{
    const FORMAT_CSV = 'csv';
    const FORMAT_TABLE = 'table';

    /**
     * @var Benchmark
     */
    protected $benchmark;

    /**
     * @var ProviderInterface
     */
    protected $provider;

    public function __construct(Benchmark $benchmark, ProviderInterface $provider)
    {
        parent::__construct();
        $this->benchmark = $benchmark;
        $this->provider = $provider;
    }

    /**
     * Configure names
     *
     * @return $this
     */
    abstract protected function configureName();

    /**
     * Configures main command options
     *
     */
    protected function configure()
    {
        $this->configureName();

        $this->addArgument(
            'database-size',
            InputArgument::REQUIRED,
            'Identifier of the created before database size'
        );

        $this->addArgument(
            'sample-size',
            InputArgument::IS_ARRAY | InputArgument::REQUIRED,
            'Size of the benchmark sample',
            []
        );

        $this->addOption(
            'database-prefix',
            'p',
            InputOption::VALUE_OPTIONAL,
            'Database name prefix',
            'benchmark_database'
        );

        $this->addOption(
            'attribute-code',
            'a',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'List of attributes to retrieve during selection process',
            ['firstname', 'lastname', 'email', 'country', 'dob']
        );

        $scopes = ['en', 'fr', 'de', 'nl', 'it'];

        $this->addOption(
            'scope',
            's',
            InputOption::VALUE_OPTIONAL,
            'Scope code for selection of data',
            $scopes[array_rand($scopes)]
        );

        $this->addOption(
            'run-count',
            'c',
            InputOption::VALUE_REQUIRED,
            'Number of run counts',
            10
        );

        $this->addOption(
            'format',
            'o', InputOption::VALUE_REQUIRED,
            'Output format, default to console table. Possible values: csv, table, json',
            'table'
        );
    }

    /**
     * Executes specified benchmark provider
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->configureProvider($input, $output);

        if ($input->getOption('database-prefix')) {
            $databaseName =  sprintf('%s_%s', $input->getOption('database-prefix'), $input->getArgument('database-size'));
        } else {
            $databaseName =  sprintf('%s_%s', $input->getOption('database-prefix'), $input->getArgument('database-size'));
        }
        $this->provider->validateDatabase($databaseName);
          
        $this->provider->setAttributeCodes($input->getOption('attribute-code'));
        $this->provider->setScopeCode($input->getOption('scope'));

        $maxBoundary = $this->provider->getMaximumBoundary();

        $this->benchmark->setSampleProvider($this->provider->getSampleProvider());

        foreach ($this->provider->getOperations() as $code => $operation) {
            $this->benchmark->addOperation($code, $operation);
        }

        $sampleSize = $input->getArgument('sample-size');
        foreach ($sampleSize as $size) {
            $this->benchmark->addSampleConfig($size, $size, $maxBoundary, $input->getOption('run-count'));
        }

        $this->provider->setup();
        $this->benchmark->execute(function () use ($databaseName) {
            $this->provider->getConnection()->closeConnection();
	    shell_exec('sudo sh -c "echo 3 > /proc/sys/vm/drop_caches"');
            shell_exec('sudo service mysql restart');
            sleep(2);
            $this->provider->validateDatabase($databaseName);
            
        });
        $this->provider->cleanup();

        $this->output($input->getOption('format'), $output, $this->benchmark->report());

        if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $report = [];
            foreach ($this->provider->getQueries() as $type => $queries) {
                foreach ($queries as $query) {
                    $report[] = ['type' => $type, 'query' => $query];
                }
            }

            $this->output($input->getOption('format'), $output, $report);
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return $this
     */
    protected function configureProvider(InputInterface $input, OutputInterface $output)
    {
        return $this;
    }

    /**
     * Outputs benchmark results
     *
     * @param $format
     * @param OutputInterface $output
     * @param array $report
     */
    private function output($format, OutputInterface $output, array $report)
    {
        $firstRow = current($report);
        if (!$firstRow) {
            throw new RuntimeException('Data output cannot be done, as no data present');
        }

        $headers = array_keys($firstRow);

        switch ($format) {
            case 'csv':
                $this->formatCsv($output, $report, $headers);
                break;
            case 'json':
                $this->formatJson($output, $report);
                break;
            case 'table':
            default:
                $this->formatTable($output, $report, $headers);
                break;
        }
    }

    /**
     * Output data in csv format
     *
     * @param OutputInterface $output
     * @param array $report
     * @param array $headers
     */
    private function formatCsv(OutputInterface $output, array $report, array $headers)
    {
        $splFileObject = new \SplTempFileObject();
        $writer = Writer::createFromFileObject($splFileObject);
        $writer->insertOne($headers);

        foreach ($report as $line) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = isset($line[$header]) ? $line[$header] : '';
            }

            $writer->insertOne($row);
        }

        $splFileObject->fseek(0);
        while (!$splFileObject->eof()) {
            $output->write($splFileObject->fread(1024));
        }
    }

    /**
     * Outputs data in json
     *
     * @param OutputInterface $output
     * @param array $report
     */
    private function formatJson(OutputInterface $output, array $report)
    {
        $output->write(json_encode($report, JSON_PRETTY_PRINT));
    }

    /**
     * Outputs data in table format
     *
     * @param OutputInterface $output
     * @param array $report
     * @param array $headers
     */
    private function formatTable(OutputInterface $output, array $report, array $headers)
    {
        $table = new Table($output);
        $table->setHeaders($headers);

        foreach ($report as $line) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = isset($line[$header]) ? substr($line[$header], 0, 128) : '';
            }

            $table->addRow($row);
        }

        $table->render();
    }



}
