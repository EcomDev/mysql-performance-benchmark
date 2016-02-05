<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Select;

class In
    extends AbstractProvider
{
    /**
     * @var Table
     */
    private $memoryTable;

    public function getOperations()
    {
        $baseLoad = function ($entityIds, callable $callback) {
            $rows = [];
            foreach ($entityIds as $id) {
                $rows[$id] = ['entity_id' => $id];
            }

            foreach ($this->attribute->getAllByType() as $type => $attributes) {
                $attributeIds = [];
                foreach ($attributes as $id => $attribute) {
                    if (!isset($this->attributeCodes[$attribute->code])) {
                        continue;
                    }
                    $attributeIds[] = $id;
                }

                if (empty($attributeIds)) {
                    continue;
                }

                $attributeTableSelect  = $this->getAttributeSelect($type, 'attribute', $attributeIds);
                $attributeTableSelect->reset(Select::ORDER);
                $callback($attributeTableSelect);

                foreach ($this->profiledQuery($attributeTableSelect) as $row) {
                    $rows[$row['entity_id']][$attributes[$row['attribute_id']]->code] = $row['value'];
                }
            }

            return $this->queryTime;
        };

        return [
            'in' => function ($ids) use ($baseLoad) {
                $this->queryCode = 'in';
                $this->reset();
                $entityCondition = $this->getConnection()->quoteInto('attribute.entity_id IN(?)', $ids);
                $baseLoad($ids, function (Select $select) use ($entityCondition) {
                    $select->where($entityCondition);
                });
                return $this->queryTime;
            },
            'memory_join' => function ($ids) use ($baseLoad) {
                $this->queryCode = 'memory_join';
                $this->reset();
                $this->insertIdsIntoMemoryTable($ids);
                $baseLoad($ids, function (Select $select) {
                    $select->join(['id' => $this->getMemoryTable()], 'attribute.entity_id = id.entity_id', []);
                });

                return $this->queryTime;
            }
        ];
    }

    private function createMemoryTable()
    {
        $this->memoryTable = $this->getConnection()->newTable($this->getTable(uniqid('entity_id')));
        $this->memoryTable->addColumn('entity_id', Table::TYPE_INTEGER, null, ['unsigned' => true, 'primary' => true]);
        $this->memoryTable->setOption('type', Mysql::ENGINE_MEMORY);
        $this->getConnection()->createTable($this->memoryTable);
        return $this;
    }

    private function getMemoryTable()
    {
        if ($this->memoryTable === null) {
            $this->createMemoryTable();
        }

        return $this->memoryTable->getName();
    }

    private function insertIdsIntoMemoryTable($ids)
    {
        $name = $this->getMemoryTable();
        $connection = $this->getConnection();
        $insertString = sprintf('INSERT INTO %s (entity_id) VALUES ', $name);
        $connection->delete($name);

        foreach ($ids as $index => $id) {
            $insertString .= ($index > 0 ? ',' : '') . '(' . (int)$id .')';
        }

        $connection->query($insertString);

        return $this;
    }

    public function cleanup()
    {
        if ($this->memoryTable) {
            $this->getConnection()->dropTable($this->memoryTable->getName());
            $this->memoryTable = null;
        }

        return parent::cleanup();
    }


    /**
     * Returns a closure that will generate a sample for benchmark
     *
     * @return \Closure
     */
    public function getSampleProvider()
    {
        return function ($size, $maximumBoundary, $runCount) {
            $step = 0;
            $samples = [];

            if (($runCount * $size) > $maximumBoundary) {
                $runCount = floor($maximumBoundary / $size);
            }

            $idSelect = $this->getMainSelect('main')
                ->columns('main.entity_id');

            if ($this->getOption('random')) {
                $idSelect->order('RAND()');
            } else {
                $idSelect->order('main.entity_id');
            }

            while ($runCount > $step && $maximumBoundary > ($size * $step)) {
                $offsetLocation = $size * $step;
                $idSelect->reset(Select::LIMIT_COUNT);

                if ($this->getOption('random')) {
                    $idSelect->limit($size);
                } else {
                    $idSelect->reset(Select::LIMIT_OFFSET);
                    $idSelect->limit($size, $offsetLocation);
                }

                $ids = $this->getConnection()->fetchCol($idSelect);
                if ($this->getOption('random')) {
                    sort($ids);
                }
                $samples[] = [$ids];
                $step ++;
            }

            return $samples;
        };
    }
}
