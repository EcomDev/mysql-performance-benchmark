<?php

namespace EcomDev\MagentoPerformance\Model;

class Benchmark
{
    /**
     * Provider of the sample data
     *
     * @var \Closure
     */
    private $sampleProvider;

    /**
     * Operation executor
     *
     * @var \Closure[]
     */
    private $operation = [];

    /**
     * Size of the sample
     *
     * @var int[][]
     */
    private $sampleConfig = [];

    /**
     * Report of the benchmark
     *
     * @var int[][][]
     */
    private $report = [];

    /**
     * Sets a provider for a benchmark sample
     *
     * @param $provider
     * @return $this
     */
    public function setSampleProvider(callable $provider)
    {
        $this->sampleProvider = $provider;
        return $this;
    }

    /**
     * @param string $code
     * @param callable $operation
     * @return $this
     */
    public function addOperation($code, callable $operation)
    {
        $this->operation[$code] = $operation;
        return $this;
    }

    public function addSampleConfig($code, $size, $maximumBoundary, $runCount = 10)
    {
        $this->sampleConfig[$code] = [$size, $maximumBoundary, $runCount];
        return $this;
    }

    /**
     * Executes sample
     *
     * @return $this
     */
    public function execute()
    {
        if (!isset($this->sampleProvider)) {
            throw new \RuntimeException('Sample provider is not specified');
        }

        if (empty($this->operation)) {
            throw new \RuntimeException('Operation for benchmark is not specified');
        }

        if (empty($this->sampleConfig)) {
            throw new \RuntimeException('At least one sample configuration needs to be added.');
        }

        $samples = [];
        foreach ($this->sampleConfig as $code => $arguments) {
            foreach (call_user_func_array($this->sampleProvider, $arguments) as $sample) {
                $samples[] = [$code, $sample];
            }
        }


        foreach ($this->operation as $operationCode => $operation) {
            foreach ($samples as list($sampleCode, $sample)) {
                $startTime = microtime(true);
                $callTime = call_user_func_array($operation, $sample);
                $totalTime = microtime(true) - $startTime;
                $times = [
                    'total' => $totalTime,
                    'internal' => $totalTime,
                    'execute' => $totalTime
                ];

                if (isset($callTime) && is_float($callTime)) {
                    $times['internal'] = $totalTime - $callTime;
                    $times['execute'] = $callTime;
                }

                $this->report[$operationCode][$sampleCode][] = $times;
            }
        }

        return $this;
    }

    /**
     * Returns average report based on all runs
     *
     * @return mixed[][]
     */
    public function report()
    {
        $report = [];

        foreach ($this->report as $operationCode => $samples) {
            foreach ($samples as $sampleCode => $timings) {
                $item = [
                    'operation' => $operationCode,
                    'sample' => $sampleCode
                ];

                $codes  = ['total', 'internal', 'execute'];

                foreach ($codes as $timingCode) {
                    $total = [];
                    foreach ($timings as $time) {
                        $total[] = $time[$timingCode];
                    }

                    $item[$timingCode] = round(array_sum($total) / count($total), 8);
                }

                $report[] = $item;
            }
        }

        return $report;
    }
}
