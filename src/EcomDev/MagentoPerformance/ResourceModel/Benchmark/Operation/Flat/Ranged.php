<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Flat;

use EcomDev\MagentoPerformance\RangeIterator;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractOperation;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractProvider;
use Magento\Framework\DB\Adapter\AdapterInterface;

class Ranged extends AbstractOperation
{
    /**
     * Table name for flat data
     *
     * @var string
     */
    private $table;

    /**
     * @param AbstractProvider $provider
     * @param string $table
     */
    public function __construct(AbstractProvider $provider, $table)
    {
        parent::__construct($provider);
        $this->table = $table;
    }

    protected function execute($args)
    {
        list($scopeId, $attributes, $columns) = $args;

        $select = $this->provider->getMainSelect();
        $select->columns(['min'=>'MIN(entity_id)', 'max' => 'MAX(entity_id)']);
        $limits = $select->query()->fetch();

        $rangedIterator = new RangeIterator($limits['min'], $limits['max'], 10000);
        $allAdditionalColumns = array_diff_key($columns, ['entity_id' => true, 'store_id' => true]);
        $dataTables = [];

        foreach (array_chunk($allAdditionalColumns, self::JOIN_LIMIT / 2, true) as $tableIndex => $additionalColumns) {
            $dataTables[$tableIndex] = $this->createCombinedTable(['entity_id' => true, 'store_id' => true], $additionalColumns, $columns, true);
        }

        foreach ($rangedIterator as $from => $to) {
            $this->generateRange($scopeId, $from, $to, $attributes, $columns, $allAdditionalColumns, $dataTables);
        }

        $this->dropTable($dataTables);
    }

    private function generateRange($scopeId, $from, $to, $attributes, $columns, $allAdditionalColumns, $dataTables)
    {
        $mainColumns = [
            'entity_id' => 'entity_id',
            'scope_id' => new \Zend_Db_Expr($this->quote($scopeId))
        ];

        foreach (array_chunk($allAdditionalColumns, self::JOIN_LIMIT / 2, true) as $tableIndex => $additionalColumns) {
            $dataTable = $dataTables[$tableIndex];

            $select = $this->select()->from(['main' => $this->getTable('entity')], [])
                ->order('main.entity_id')
                ->where('main.entity_id >= ?', $from)
                ->where('main.entity_id < ?', $to);

            $selectColumns = $mainColumns;

            foreach ($additionalColumns as $columnCode => $definition) {
                if (!isset($attributes[$columnCode])) {
                    continue;
                }

                $attribute = $attributes[$columnCode];

                $attributeDefaultTableAlias = sprintf('attribute_%s_default', $columnCode);
                $attributeStoreTableAlias = sprintf('attribute_%s_store', $columnCode);

                $select->joinLeft(
                    [$attributeDefaultTableAlias  => $this->provider->getTable(['entity', $attribute->type])],
                    $this->provider->andCondition([
                        sprintf('%s.entity_id = %s.entity_id', $attributeDefaultTableAlias, 'main'),
                        sprintf('%s.attribute_id = ?', $attributeDefaultTableAlias) => $attribute->id,
                        sprintf('%s.scope_id = ?', $attributeDefaultTableAlias) => 0
                    ]),
                    []
                );

                $select->joinLeft(
                    [$attributeStoreTableAlias  => $this->provider->getTable(['entity', $attribute->type])],
                    $this->provider->andCondition([
                        sprintf('%s.entity_id = %s.entity_id', $attributeStoreTableAlias, 'main'),
                        sprintf('%s.attribute_id = ?', $attributeStoreTableAlias) => $attribute->id,
                        sprintf('%s.scope_id = ?', $attributeStoreTableAlias) => $scopeId
                    ]),
                    []
                );

                $selectColumns[$definition['COLUMN_NAME']] = $this->getConnection()->getCheckSql(
                    sprintf('%s.entity_id IS NULL', $attributeStoreTableAlias),
                    sprintf('%s.value', $attributeDefaultTableAlias),
                    sprintf('%s.value', $attributeStoreTableAlias)
                );
            }

            $select->columns($selectColumns);

            $this->getConnection()->truncateTable($dataTable->getName());
            $this->profiledQuery(
                $this->getConnection()->insertFromSelect(
                    $select,
                    $dataTable->getName(),
                    array_keys($selectColumns)
                )
            );
        }


        $firstName = false;
        $select = $this->getConnection()->select();
        $dataColumns = [];
        foreach ($dataTables as $table) {
            if ($firstName === false) {
                $select->from($table->getName(), []);
                $firstName = $table->getName();
            } else {
                $select->join(
                    $table->getName(),
                    $this->provider->andCondition([
                        sprintf('%s.entity_id = %s.entity_id', $table->getName(), $firstName),
                        sprintf('%s.scope_id = %s.scope_id', $table->getName(), $firstName)
                    ]),
                    []
                );
            }

            $dataColumns[$table->getName()] = $table->getColumns();
        }

        $insertColumns = [];
        foreach ($dataColumns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                $insertColumns[$column['COLUMN_NAME']] = sprintf('%s.%s', $table, $column['COLUMN_NAME']);
            }
        }

        $select->columns($insertColumns);

        $this->profiledQuery(
            $this->getConnection()->insertFromSelect(
                $select,
                $this->getTable($this->table),
                array_keys($insertColumns),
                AdapterInterface::INSERT_ON_DUPLICATE
            )
        );

    }
}
