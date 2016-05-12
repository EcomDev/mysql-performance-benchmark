<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Flat;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractOperation;
use EcomDev\MagentoPerformance\ResourceModel\Benchmark\AbstractProvider;
use Magento\Framework\DB\Adapter\AdapterInterface;

class Regular extends AbstractOperation
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

        $mainColumns = [
            'entity_id' => 'entity_id',
            'scope_id' => new \Zend_Db_Expr($this->quote($scopeId))
        ];;

        $allAdditionalColumns = array_diff_key($columns, $mainColumns);
        $dataTables = [];

        foreach (array_chunk($allAdditionalColumns, self::JOIN_LIMIT / 2, true) as $additionalColumns) {
            $dataTable = $this->createCombinedTable($mainColumns, $additionalColumns, $columns);
            $select = $this->provider->getMainSelect('main');

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

            $this->profiledQuery(
                $this->getConnection()->insertFromSelect(
                    $select,
                    $dataTable->getName(),
                    array_keys($selectColumns)
                )
            );

            $dataTables[] = $dataTable;
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

        $this->dropTable($dataTables);
    }
}
