<?php

namespace EcomDev\MagentoPerformance\Console;

use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Console\Application;

class ApplicationBuilder
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    private $commandClasses = [
        Command\DatabaseCreate::class,
        Command\BenchmarkQuery::class,
        Command\BenchmarkFlat::class,
        Command\BenchmarkFlatData::class,
        Command\BenchmarkLimit::class,
        Command\BenchmarkExport::class,
        Command\NormalizeBenchmark::class,
        Command\MergeBenchmark::class
    ];

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Returns a new console application
     *
     * @return Application
     */
    public function createApplication()
    {
        /** @var Application $application */
        $application = $this->objectManager->create(
            Application::class, ['EcomDev Magento Database Performance Tool', '1.0']
        );

        $application->addCommands($this->createCommands());
        return $application;
    }

    private function createCommands()
    {
        $commands = [];
        foreach ($this->commandClasses as $class) {
            $commands[] = $this->objectManager->create($class);
        }

        return $commands;
    }
}
