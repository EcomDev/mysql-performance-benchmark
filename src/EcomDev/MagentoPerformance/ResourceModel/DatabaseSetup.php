<?php

namespace EcomDev\MagentoPerformance\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class DatabaseSetup extends AbstractDb
{
    const FK_CASCADE = AdapterInterface::FK_ACTION_CASCADE;
    const IDX_UNIQUE = AdapterInterface::INDEX_TYPE_UNIQUE;
    const IDX_INDEX = AdapterInterface::INDEX_TYPE_INDEX;

    /**
     * @var string[][][]
     */
    private $tableStructure;

    /**
     * @var string[][]
     */
    private $tableData;

    /**
     * Sets table structure
     *
     */
    protected function _construct()
    {
        $this->populateTableArray()
            ->populateTableDataArray();
    }

    public function createSchema($databaseName)
    {
        $connection = $this->getConnection();
        $connection->startSetup();
        $connection->query(
            sprintf('DROP DATABASE IF EXISTS %s', $this->getConnection()->quoteIdentifier($databaseName))
        );
        $connection->query(
            sprintf('CREATE DATABASE %s', $this->getConnection()->quoteIdentifier($databaseName))
        );
        $connection->query(
            sprintf('USE %s', $this->getConnection()->quoteIdentifier($databaseName))
        );

        $defaultInfo = ['columns' => [], 'indexes' => [], 'constraints' => []];
        $defaultColumn = [Table::TYPE_INTEGER, null, []];
        $defaultIndex = [1 => self::IDX_INDEX];
        $defaultFk = [3 => self::FK_CASCADE];

        foreach ($this->tableStructure as $tableName => $info) {
            $info += $defaultInfo;
            $table = $this->getConnection()->newTable($tableName);
            foreach ($info['columns'] as $columnName => $column) {
                list($type, $size, $options) = $column + $defaultColumn;
                $table->addColumn($columnName, $type, $size, $options);
            }
            foreach ($info['indexes'] as $index) {
                list($column, $type) = $index + $defaultIndex;
                $table->addIndex($connection->getIndexName($tableName, $column, $type), $column, ['type' => $type]);
            }

            foreach ($info['constraints'] as $constraint) {
                list($column, $referenceTable, $referenceColumn, $onDelete) = $constraint + $defaultFk;
                $table->addForeignKey(
                    $connection->getForeignKeyName($tableName, $column, $referenceTable, $referenceColumn),
                    $column,
                    $referenceTable,
                    $referenceColumn,
                    $onDelete
                );
            }

            $connection->createTable($table);
        }

        $connection->beginTransaction();

        foreach ($this->tableData as $table => $rows) {
            $connection->insertOnDuplicate($table, $rows);
        }

        $connection->commit();
        $connection->endSetup();
    }

    /**
     * Populates table array
     *
     * @return $this
     */
    private function populateTableArray()
    {
        $this->tableStructure = [
            'attribute' => [
                'columns' => [
                    'attribute_id' => [
                        Table::TYPE_INTEGER,
                        null,
                        ['primary' => true, 'identity' => true, 'unsigned' => true]
                    ],
                    'code' => [Table::TYPE_TEXT, 255],
                    'type' => [Table::TYPE_TEXT, 255],
                    'required' => [Table::TYPE_DECIMAL, [4,2]],
                    'scope' => [Table::TYPE_INTEGER, 1],
                    'generator' => [Table::TYPE_TEXT, 255]
                ],
                'indexes' => [
                    ['code', self::IDX_UNIQUE]
                ]
            ],
            'scope' => [
                'columns' => [
                    'scope_id' => [
                        Table::TYPE_INTEGER,
                        null,
                        ['primary' => true, 'identity' => true, 'unsigned' => true]
                    ],
                    'code' => [Table::TYPE_TEXT, 255],
                    'locale' => [Table::TYPE_TEXT, 5],
                ],
                'indexes' => [
                    ['code', self::IDX_UNIQUE]
                ]
            ],
            'entity' => [
                'columns' => [
                    'entity_id' => [
                        Table::TYPE_INTEGER,
                        null,
                        ['primary' => true, 'identity' => true, 'unsigned' => true]
                    ],
                    'code' => [Table::TYPE_TEXT, 255]
                ],
                'indexes' => [
                    ['code', self::IDX_UNIQUE]
                ]
            ],
            'entity_flat' => [
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
            'entity_varchar' => $this->generateEavTable(Table::TYPE_TEXT, 255),
            'entity_text' => $this->generateEavTable(Table::TYPE_TEXT, '50k'),
            'entity_decimal' => $this->generateEavTable(Table::TYPE_DECIMAL, [12,4]),
            'entity_datetime' => $this->generateEavTable(Table::TYPE_DATETIME, null),
            'entity_int' => $this->generateEavTable(Table::TYPE_INTEGER, null),
        ];

        return $this;
    }

    private function populateTableDataArray()
    {
        $this->tableData = [];
        $this->tableData['attribute'] = [
            ['code' => 'firstname', 'type' => 'varchar', 'required' => 1, 'scope' => 0, 'generator' => 'firstName'],
            ['code' => 'lastname', 'type' => 'varchar', 'required' => 1, 'scope' => 0, 'generator' => 'lastName'],
            ['code' => 'company', 'type' => 'varchar', 'required' => 0.4, 'scope' => 1, 'generator' => 'company'],
            ['code' => 'nickname', 'type' => 'varchar', 'required' => 0.2, 'scope' => 0, 'generator' => 'userName'],
            ['code' => 'email', 'type' => 'varchar', 'required' => 1, 'scope' => 0, 'generator' => 'email'],
            ['code' => 'title', 'type' => 'varchar', 'required' => 0.3, 'scope' => 1, 'generator' => 'title'],
            ['code' => 'dob', 'type' => 'datetime', 'required' => 1, 'scope' => 0, 'generator' => 'dateTimeBetween:-80 years:-12 years'],
            ['code' => 'phone', 'type' => 'varchar', 'required' => 1, 'scope' => 0, 'generator' => 'phoneNumber'],
            ['code' => 'country', 'type' => 'varchar', 'required' => 1, 'scope' => 1, 'generator' => 'countryCode'],
            ['code' => 'city', 'type' => 'varchar', 'required' => 0.6, 'scope' => 1, 'generator' => 'city'],
            ['code' => 'postcode', 'type' => 'varchar', 'required' => 0.2, 'scope' => 1, 'generator' => 'postcode'],
            ['code' => 'address', 'type' => 'text', 'required' => 0.1, 'scope' => 1, 'generator' => 'streetAddress'],
            ['code' => 'bio', 'type' => 'text',  'required' => 0.21, 'scope' => 1, 'generator' => 'realText:200'],
            ['code' => 'notes', 'type' => 'text', 'required' => 0.11, 'scope' => 1, 'generator' => 'realText:20'],
            ['code' => 'is_active', 'type' => 'int', 'required' => 1, 'scope' => 1, 'generator' => 'boolean:90'],
            ['code' => 'number_of_orders', 'type' => 'int', 'required' => 0.2, 'scope' => 1, 'generator' => 'randomNumber'],
            ['code' => 'balance', 'type' => 'decimal', 'required' => 0.2,  'scope' => 1, 'generator' => 'randomFloat'],
            ['code' => 'currency', 'type' => 'varchar', 'required' => 0.7, 'scope' => 1, 'generator' => 'currencyCode'],
            ['code' => 'spent_amount', 'type' => 'decimal', 'required' => 0.1, 'scope' => 1, 'generator' => 'randomFloat']
        ];

        $this->tableData['scope'] = [
            ['scope_id' => 0, 'code' => 'default', 'locale' => ''],
            ['scope_id' => 1, 'code' => 'en', 'locale' => 'en_GB'],
            ['scope_id' => 2, 'code' => 'nl', 'locale' => 'nl_NL'],
            ['scope_id' => 3, 'code' => 'it', 'locale' => 'it_IT'],
            ['scope_id' => 4, 'code' => 'fr', 'locale' => 'fr_FR'],
            ['scope_id' => 5, 'code' => 'de', 'locale' => 'de_DE']
        ];

        return $this;
    }

    /**
     * Creates a EAV table
     *
     * @param $valueType
     * @param null $valueLength
     * @return array
     */
    private function generateEavTable($valueType, $valueLength = null)
    {
        return [
            'columns' => [
                'value_id' => [
                    Table::TYPE_INTEGER,
                    null,
                    ['primary' => true, 'identity' => true, 'unsigned' => true]
                ],
                'entity_id' => [
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true]
                ],
                'scope_id' => [
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true]
                ],
                'attribute_id' => [
                    Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true]
                ],
                'value' => [$valueType, $valueLength],
            ],
            'constraints' => [
                ['entity_id', 'entity', 'entity_id'],
                ['scope_id', 'scope', 'scope_id'],
                ['attribute_id', 'attribute', 'attribute_id']
            ]
        ];
    }

}
