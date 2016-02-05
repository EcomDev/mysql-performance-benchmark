<?php

namespace EcomDev\MagentoPerformance\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Faker\Generator as FakerGenerator;
use Faker\Factory as Faker;

/**
 * Data Generator
 *
 * @example for checking generated data
 * SELECT COUNT(v.value_id), a.code, s.code FROM entity_[type] v
 *  INNER JOIN scope s ON s.scope_id = v.scope_id
 *  INNER JOIN attribute a ON a.attribute_id = v.attribute_id
 *  GROUP BY v.attribute_id, v.scope_id;
 */
class Generator extends AbstractDb
{
    /**
     * @var Attribute
     */
    private $attribute;

    /**
     * @var Scope
     */
    private $scope;

    /**
     * @var FakerGenerator
     */
    private $faker;

    /**
     * Scope faker
     *
     * @var FakerGenerator[]
     */
    private $scopeFaker;

    /**
     * Batch for a database
     *
     * @var array
     */
    private $batch;

    /**
     * @var array
     */
    private $defaultBatch = [
        'entity' => [],
        'value_index' => [],
        'values' => []
    ];

    public function __construct(Attribute $attribute, Scope $scope, Context $context, $connectionName = null)
    {
        parent::__construct($context, $connectionName);

        $this->attribute = $attribute;
        $this->scope = $scope;
    }


    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('entity', 'entity_id');
    }

    private function initFaker()
    {
        $this->faker = Faker::create();
        foreach ($this->scope->getCodes() as $code) {
            $this->scopeFaker[$code] = Faker::create($this->scope->getLocale($code));
        }
    }

    private function reset()
    {
        $this->scope->reset();
        $this->attribute->reset();
        $this->batch = $this->defaultBatch;
        $this->initFaker();
    }

    public function generate($databaseName, $size, $batchSize = 1000)
    {
        $this->getConnection()->query(sprintf('USE %s', $databaseName));
        $this->reset();

        $scopes = array_keys($this->scopeFaker);
        $numberOfScopes = count($scopes);
        $currentBatch = 0;
        $createdRecords = 0;

        while ($size > $createdRecords) {
            if ($currentBatch >= $batchSize) {
                $this->flush();
                $currentBatch = 0;
            }

            $createdRecords ++;
            $currentBatch ++;
            $code = $this->faker->uuid;

            $this->batch['entity'][$code] = ['code' => $code];
            $this->generateAttributes($this->faker, $this->attribute->getAll(), $code, 0);

            if ($this->faker->boolean(66)) {
                $currentScopes = $this->faker->randomElements(
                    $scopes,
                    $this->faker->numberBetween(1, $numberOfScopes)
                );

                foreach ($currentScopes as $scopeCode) {
                    $this->generateAttributes(
                        $this->scopeFaker[$scopeCode],
                        $this->attribute->getAllScopeAware(),
                        $code,
                        $this->scope->getId($scopeCode)
                    );
                }
            }
        }

        if ($currentBatch > 0) {
            $this->flush();
        }

        return $this;
    }

    private function generateAttributes(FakerGenerator $faker, $attributes, $code, $scopeId)
    {
        foreach ($attributes as $attribute) {
            $attributeFaker = $faker;
            if (1 - $attribute->required > 0.001) {
                $attributeFaker = $faker->optional($attribute->required);
            }

            $value = $attributeFaker->format($attribute->generatorMethod, $attribute->generatorArguments);

            if ($value !== null) {
                if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                }

                $valueKey = sprintf('%s-%s-%s', $code, $attribute->id, $scopeId);

                $this->batch['value'][$attribute->type][$valueKey] = [
                    'entity_id' => null,
                    'attribute_id' => $attribute->id,
                    'scope_id' => $scopeId,
                    'value' => $value
                ];

                $this->batch['value_index'][$code][] = [$attribute->type, $valueKey];
            }
        }

        return $this;
    }

    private function flush()
    {
        $this->getConnection()->beginTransaction();

        $this->getConnection()->insertOnDuplicate(
            $this->getTable('entity'), $this->batch['entity']
        );

        $select = $this->getConnection()->select()->from(
                $this->getMainTable(), ['code', $this->getIdFieldName()]
            )
            ->where('code IN(?)', array_keys($this->batch['entity']))
        ;

        foreach ($select->query() as $row) {
            if (isset($this->batch['value_index'][$row['code']])) {
                foreach ($this->batch['value_index'][$row['code']] as $combination) {
                    list ($type, $code) = $combination;
                    $this->batch['value'][$type][$code]['entity_id'] = $row[$this->getIdFieldName()];
                }
                unset($this->batch['value_index'][$row['code']]);
            }
        }

        foreach ($this->batch['value_index'] as $code => $combinations) {
            foreach ($combinations as $combination) {
                list ($type, $code) = $combination;
                unset($this->batch['value'][$type][$code]);
            }
        }

        foreach ($this->batch['value'] as $type => $values) {
            if (!$values) {
                continue;
            }

            $this->getConnection()->insertOnDuplicate(
                $this->getTable(['entity', $type]), $values
            );
        }

        $this->getConnection()->commit();
        $this->batch = $this->defaultBatch;
    }
}
