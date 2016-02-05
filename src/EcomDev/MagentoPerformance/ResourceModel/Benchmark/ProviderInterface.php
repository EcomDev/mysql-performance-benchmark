<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

interface ProviderInterface
{
    /**
     * Returns callable
     *
     * @return callable[]
     */
    public function getOperations();

    /**
     * Returns sample provider
     *
     * @return callable
     */
    public function getSampleProvider();

    /**
     * Returns total size
     *
     * @return int
     */
    public function getMaximumBoundary();

    /**
     * Sets scope code for data
     *
     * @param string $code
     * @return $this
     */
    public function setScopeCode($code);

    /**
     * List of attributes to retrieve
     *
     * @param array $code
     * @return mixed
     */
    public function setAttributeCodes(array $code);

    /**
     * Validates that database is valid and sets it as a connection
     *
     * @param string $databaseName
     * @throws \RuntimeException when database is not created
     * @return $this
     */
    public function validateDatabase($databaseName);

    /**
     * @return string[]
     */
    public function getQueries();

    /**
     * Sets an option
     *
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function setOption($name, $value);

    public function cleanup();

    public function setup();
}
