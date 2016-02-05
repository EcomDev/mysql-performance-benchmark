<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

use Magento\Framework\DB\Select;

class Limit
    extends AbstractProvider
{

    public function getOperations()
    {
        $baseSelect = function () {
            $select = $this->getMainSelect('main');
            $select->columns('entity_id', 'main');
            $this->limitFlatActive($select);
            $select->order('flat.firstname');
            $attributes = $this->attribute->getAll();

            foreach ($this->attributeCodes as $code) {
                if (!isset($attributes[$code])) {
                    continue;
                }

                $this->joinAttribute($select, $attributes[$code], 'main');
            }

            return $select;
        };

        return [
            'regular' => function ($offset, $limit) use ($baseSelect) {
                $this->reset();
                $select = $baseSelect();
                $select->limit($limit, $offset);

                $rows = [];
                foreach ($this->profiledQuery($select) as $row) {
                    $rows[] = $row;
                }

                return $this->queryTime;
            },
            'ranged' => function ($offset, $limit) use ($baseSelect) {
                $this->reset();
                $idSelect = $this->getConnection()->select();
                $idSelect->from(['flat' => $this->getTable('entity_flat')], []);
                $idSelect->where('flat.scope_id = ?', $this->scope->getId($this->scopeCode));
                $idSelect->where('flat.is_active = ?', 1);
                $idSelect->columns('entity_id', 'flat');
                $idSelect->order('flat.firstname');
                $idSelect->limit($limit, $offset);

                $rows = [];
                foreach ($this->profiledQuery($idSelect) as $row) {
                    $rows[$row['entity_id']] = $row;
                }

                $select = $baseSelect();
                $select->where('main.entity_id IN(?)', array_keys($rows));

                foreach ($this->profiledQuery($select) as $row) {
                    $rows[$row['entity_id']] += $row;
                }

                return $this->queryTime;
            }
        ];
    }

    protected function configureBoundarySelect(Select $select)
    {
        $this->limitFlatActive($select);
        return parent::configureBoundarySelect($select);
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

            if ($runCount * $size > $maximumBoundary) {
                $runCount = floor($maximumBoundary / $size);
            }

            $factor = 1;
            while ($runCount > $step) {
                $factor *= -1;
                $offsetLocation = $size * $step;

                if ($factor < 0) {
                    $offsetLocation = max($maximumBoundary - $offsetLocation - $size, 0);
                }

                $samples[] = [$offsetLocation, $size];
                $step ++;
            }

            return $samples;
        };
    }
}
