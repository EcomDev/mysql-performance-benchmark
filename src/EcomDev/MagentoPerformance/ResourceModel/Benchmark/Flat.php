<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

class Flat
    extends AbstractProvider
{
    protected $batchSize = 20000;

    public function getOperations()
    {
        $rangedFlat = function ($scopeId, $attributes, $columns, $from, $to) {
            $mainColumns = [
                'entity_id' => 'entity_id',
                'scope_id' => new \Zend_Db_Expr($this->getConnection()->quote($scopeId))
            ];

            $select = $this->getMainSelect('main');
            $select->columns($mainColumns)
                ->where('main.entity_id >= ?', $from)
                ->where('main.entity_id < ?', $to);

            $this->profiledQuery($this->getConnection()->insertFromSelect(
                $select,
                $this->getTable('entity_flat'),
                array_keys($mainColumns)
            ));

            foreach ($columns as $columnCode => $definition) {
                if (isset($mainColumns[$columnCode]) || !isset($attributes[$columnCode])) {
                    continue;
                }

                $attribute = $attributes[$columnCode];

                $select = $this->getConnection()->select();
                $select
                    ->join(
                        ['attribute' => $this->getTable(['entity', $attribute->type])],
                        $this->andCondition([
                            sprintf('%s.entity_id = %s.entity_id', 'attribute', 'main'),
                            sprintf('%s.attribute_id = ?', 'attribute') => $attribute->id,
                            sprintf('%s.scope_id = ?', 'attribute') => 0
                        ]),
                        [$attribute->code => 'attribute.value']
                    )
                    ->where('main.scope_id = ?', $scopeId)
                    ->where('main.entity_id >= ?', $from)
                    ->where('main.entity_id < ?', $to);

                $this->profiledQuery(
                    $this->getConnection()->updateFromSelect($select, ['main' => $this->getTable('entity_flat')])
                );

                $select = $this->getConnection()->select();
                $select
                    ->join(
                        ['attribute' => $this->getTable(['entity', $attribute->type])],
                        $this->andCondition([
                            sprintf('%s.entity_id = %s.entity_id', 'attribute', 'main'),
                            sprintf('%s.attribute_id = ?', 'attribute') => $attribute->id,
                            sprintf('%s.scope_id = %s.scope_id', 'attribute', 'main')
                        ]),
                        [$attribute->code => 'attribute.value']
                    )
                    ->where('main.scope_id = ?', $scopeId)
                    ->where('main.entity_id >= ?', $from)
                    ->where('main.entity_id < ?', $to);

                $this->profiledQuery(
                    $this->getConnection()->updateFromSelect($select, ['main' => $this->getTable('entity_flat')])
                );
            }
        };

        return [
            'flat' => function ($scopeId) use ($rangedFlat) {
                $this->reset();
                $this->queryCode = 'flat';
                $scopeId = $this->scope->getId($scopeId);
                $columns = $this->getConnection()->describeTable($this->getTable('entity_flat'));
                $attributes = $this->attribute->getAll();

                $select = $this->getMainSelect();
                $select->columns(['min'=>'MIN(entity_id)', 'max' => 'MAX(entity_id)']);

                $limits = $select->query()->fetch();

                $firstItem = $limits['min'];

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

                $this->getConnection()->multiQuery(implode('; ', $before));
                do {
                    $lastItem = min($limits['max'] + 1, $firstItem + $this->batchSize);
                    $rangedFlat($scopeId, $attributes, $columns, $firstItem, $lastItem);
                    $firstItem = $lastItem;
                } while ($lastItem < $limits['max']);

                $this->getConnection()->multiQuery(implode('; ', $after));

                return $this->queryTime;
            }
        ];
    }

    /**
     * Returns a closure that will generate a sample for benchmark
     *
     * @return \Closure
     */
    public function getSampleProvider()
    {
        return function () {
            $this->getConnection()->truncateTable($this->getTable('entity_flat'));
            $sample = [];
            foreach ($this->scope->getCodes() as $code) {
                $sample[] = [$code];
            }

            return $sample;
        };
    }
}
