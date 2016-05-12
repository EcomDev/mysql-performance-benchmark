<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;


use EcomDev\MagentoPerformance\Model\SampleCodeAwareInterface;
use Magento\Framework\DB\Ddl\Table;

abstract class AbstractOperation implements SampleCodeAwareInterface
{
    /**
     * @var AbstractProvider
     */
    protected $provider;

    const JOIN_LIMIT = 62;

    /**
     * AbstractFlatGenerator constructor.
     * @param AbstractProvider $provider
     */
    public function __construct(AbstractProvider $provider)
    {
        $this->provider = $provider;
    }

    protected function getConnection()
    {
        return $this->provider->getConnection();
    }

    protected function quote($value)
    {
        return $this->provider->getConnection()->quote($value);
    }

    protected function getTable($table)
    {
        return $this->provider->getTable($table);
    }

    protected function select()
    {
        return $this->provider->select();
    }

    protected function profiledQuery($query, array $bind = [])
    {
        return $this->provider->profiledQuery($query, $bind);
    }

    abstract protected function execute($args);

    protected function createCombinedTable($mainColumns, $additionalColumns, $allColumns)
    {
        if (is_int(key($additionalColumns))) {
            $additionalColumns = array_combine($additionalColumns, $additionalColumns);
        }

        $columns = array_intersect_key($allColumns, $mainColumns);
        $columns += array_intersect_key($allColumns, $additionalColumns);

        return $this->createTable($columns);
    }

    protected function createTable($columns)
    {
        $table = $this->getConnection()->newTable(uniqid('tmp_table'));

        foreach ($columns as $columnData) {
            $columnInfo = $this->getConnection()->getColumnCreateByDescribe($columnData);
            $table->addColumn(
                $columnInfo['name'],
                $columnInfo['type'],
                $columnInfo['length'],
                $columnInfo['options'],
                $columnInfo['comment']
            );
        }

        $table->setOption('type', 'MEMORY');
        $this->getConnection()->createTable($table);
        return $table;
    }

    /**
     * @param Table[]|Table $table
     * @return $this
     */
    protected function dropTable($table)
    {
        if (is_array($table)) {
            foreach ($table as $item) {
                $this->dropTable($item);
            }

            return $this;
        }

        $this->getConnection()->dropTable($table->getName());
        return $this;
    }

    public function __invoke()
    {
        $this->provider->reset();
        $this->execute(func_get_args());
        return $this->provider->getQueryTime();
    }

    public function setSampleCode($sampleCode)
    {
        $this->provider->setQueryCode($sampleCode);
    }


}
