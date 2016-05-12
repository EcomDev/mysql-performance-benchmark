<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

use EcomDev\MagentoPerformance\ResourceModel\Benchmark\Operation\Flat as FlatOperation;

use EcomDev\MagentoPerformance\ResourceModel\DatabaseSetup;
use Magento\Framework\DB\Ddl\Table;
use EcomDev\MagentoPerformance\ResourceModel\Attribute;
use EcomDev\MagentoPerformance\ResourceModel\Scope;
use Magento\Framework\Model\ResourceModel\Db\Context;

class FlatData
    extends AbstractProvider
{
    protected $batchSize = 20000;
    /**
     * @var DatabaseSetup
     */
    private $databaseSetup;

    public function __construct(DatabaseSetup $databaseSetup, Attribute $attribute, Scope $scope, Context $context, $connectionName = null)
    {
        $this->databaseSetup = $databaseSetup;
        parent::__construct($attribute, $scope, $context, $connectionName);
    }

    public function createFlatDataTable()
    {
        $this->databaseSetup->start();
        $tables = [
            'entity_flat_data' => [
                'columns' => [
                    'entity_id' => [
                        Table::TYPE_INTEGER,
                        null,
                        ['primary' => true, 'unsigned' => true]
                    ],
                    'scope_id' => [
                        Table::TYPE_INTEGER,
                        null,
                        ['primary' => true, 'unsigned' => true]
                    ],
                    'firstname' => [Table::TYPE_TEXT, 255],
                    'dob' => [Table::TYPE_DATETIME],
                    'is_active' => [Table::TYPE_INTEGER]
                ],
                'indexes' => [
                    ['firstname'],
                    ['dob'],
                    ['is_active'],
                ],
                'constraints' => [
                    ['entity_id', 'entity', 'entity_id'],
                    ['scope_id', 'scope', 'scope_id']
                ]
            ],
        ];

        foreach ($this->attribute->getAll() as $item) {
            if (!isset($tables['entity_flat_data']['columns'][$item->code])) {
                $tables['entity_flat_data']['columns'][$item->code] = $this->attributeToColumn($item);
            }
        }

        $this->databaseSetup->createTables($tables);
        $this->databaseSetup->end();
    }

    private function attributeToColumn($item)
    {
        switch ($item->type) {
            case 'varchar':
                return [Table::TYPE_TEXT, 255];
                break;
            case 'datetime':
                return [Table::TYPE_DATETIME];
                break;
            case 'text':
                return [Table::TYPE_TEXT, '1m'];
                break;
            case 'int':
                return [Table::TYPE_INTEGER];
                break;
            case 'decimal':
                return [Table::TYPE_DECIMAL, [12,4]];
                break;

        }

        return [Table::TYPE_TEXT, 255];
    }

    public function getOperations()
    {
        return [
            'flat_regular' => function ($scopeId) {
                return $this->executeOperation(
                    new FlatOperation\Regular($this, 'entity_flat_data'),
                    'flat_data_regular',
                    $scopeId
                );
            },
            'flat_ranged' => function ($scopeId)  {
                return $this->executeOperation(
                    new FlatOperation\Ranged($this, 'entity_flat_data'),
                    'flat_data_ranged',
                    $scopeId
                );
            }
        ];
    }
    public function setup()
    {
        parent::setup();
        if (!$this->getConnection()->isTableExists('entity_flat_data')) {
            $this->createFlatDataTable();
        }
        return $this;
    }

    private function executeOperation(AbstractOperation $operation, $code, $scopeId)
    {
        $this->queryCode = $code;
        $scopeId = $this->scope->getId($scopeId);

        $before = [
            'SET autocommit=0',
            'SET unique_checks=0',
            'SET foreign_key_checks=0',
        ];

        $after = [
            'COMMIT',
            'SET autocommit=1',
            'SET unique_checks=1',
            'SET foreign_key_checks=1'
        ];

        $columns = $this->getConnection()->describeTable($this->getTable('entity_flat_data'));
        $attributes = $this->attribute->getAll();

        $this->getConnection()->multiQuery(implode('; ', $before));
        $queryTime = $operation($scopeId, $attributes, $columns);;
        $this->getConnection()->multiQuery(implode('; ', $after));
        return $queryTime;
    }

    /**
     * Returns a closure that will generate a sample for benchmark
     *
     * @return \Closure
     */
    public function getSampleProvider()
    {
        return function () {
            $this->getConnection()->truncateTable($this->getTable('entity_flat_data'));
            $sample = [];
            foreach ($this->scope->getCodes() as $code) {
                $sample[] = [$code];
            }

            return $sample;
        };
    }
}
