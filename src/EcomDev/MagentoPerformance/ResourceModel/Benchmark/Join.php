<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

use Magento\Framework\DB\Select;

class Join
    extends AbstractProvider
{

    public function getOperations()
    {
        $baseJoin = function ($offset, $limit, \Closure $callback = null) {
            $this->reset();
            $select = $this->getMainSelect('main');
            $select->columns('entity_id', 'main');
            $attributes = $this->attribute->getAll();

            if ($callback !== null) {
                $callback($select);
            }

            foreach ($this->attributeCodes as $code) {
                if (!isset($attributes[$code])) {
                    continue;
                }

                $this->joinAttribute($select, $attributes[$code], 'main');
            }

            $select->limit($limit, $offset);

            $rows = [];
            foreach ($this->profiledQuery($select) as $row) {
                $rows[] = $row;
            }

            return $this->queryTime;
        };

        $baseSeparate = function ($offset, $limit, \Closure $callback = null) {
            $this->reset();
            $select = $this->getMainSelect('main');
            $select->columns('entity_id', 'main');
            $select->limit($limit, $offset);

            if ($callback !== null) {
                $callback($select);
            }

            $rows = [];
            foreach ($this->profiledQuery($select) as $row) {
                $rows[$row['entity_id']] = $row;
            }

            $entityIds = array_keys($rows);

            if (!$entityIds) {
                return $this->queryTime;
            }

            $entityCondition = $this->getConnection()->quoteInto('attribute.entity_id IN(?)', $entityIds);

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
                $attributeTableSelect->where($entityCondition);

                foreach ($this->profiledQuery($attributeTableSelect) as $row) {
                    $rows[$row['entity_id']][$attributes[$row['attribute_id']]->code] = $row['value'];
                }
            }

            return $this->queryTime;
        };

        $joinFilter = function (Select $select) {
            $valueExpression = $this->joinAttribute($select, $this->attribute->getAll()['is_active'], 'main');
            $select->where(sprintf('%s = ?', $valueExpression), 1);
        };

        $flatFilter = function (Select $select) {
            $this->limitFlatActive($select);
        };

        return [
            'join' => function ($offset, $limit) use ($baseJoin) {
                $this->queryCode = 'join';
                return $baseJoin($offset, $limit);
            },

            'join_filter_join' => function ($offset, $limit) use ($baseJoin, $joinFilter) {
                $this->queryCode = 'join_filter_join';
                return $baseJoin($offset, $limit, $joinFilter);
            },

            'join_filter_flat' => function ($offset, $limit) use ($baseJoin, $flatFilter) {
                $this->queryCode = 'join_filter_flat';
                return $baseJoin($offset, $limit);
            },

            'separate_query' => function ($offset, $limit) use ($baseSeparate) {
                $this->queryCode = 'separate_query';
                return $baseSeparate($offset, $limit);
            },

            'separate_query_filter_join' => function ($offset, $limit) use ($baseSeparate, $joinFilter) {
                $this->queryCode = 'separate_query_filter_join';
                return $baseSeparate($offset, $limit, $joinFilter);
            },

            'separate_query_filter_flat' => function ($offset, $limit) use ($baseSeparate, $flatFilter) {
                $this->queryCode = 'separate_query_filter_flat';
                return $baseSeparate($offset, $limit, $flatFilter);
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
        return $this->getLimitSample();
    }
}
