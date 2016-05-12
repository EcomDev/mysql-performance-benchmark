<?php

namespace EcomDev\MagentoPerformance\ResourceModel\Benchmark;

use EcomDev\MagentoPerformance\ResourceModel\Attribute;
use EcomDev\MagentoPerformance\ResourceModel\Scope;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

abstract class AbstractProvider
    extends AbstractDb
    implements ProviderInterface
{

    protected $queryCode = 'default';

    /**
     * @var Scope
     */
    protected $scope;

    /**
     * @var string
     */
    protected $scopeCode;

    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var int
     */
    protected $queryTime;

    /**
     * @var string[]
     */
    protected $queries;

    protected $attributeCodes = [];

    /**
     * @var mixed[]
     */
    protected $option = [];

    public function __construct(Attribute $attribute, Scope $scope, Context $context, $connectionName = null)
    {
        parent::__construct($context, $connectionName);
        $this->attribute = $attribute;
        $this->scope = $scope;
    }


    protected function _construct()
    {
        $this->_init('entity', 'entity_id');
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMaximumBoundary()
    {
        $select = $this->getMainSelect('main');
        $this->configureBoundarySelect($select);
        $select->reset(Select::COLUMNS);
        $select->columns(['COUNT(main.entity_id)'], 'main');

        return $this->getConnection()->fetchOne($select);
    }

    /**
     * Returns time of all executed queries
     *
     * @return int
     */
    public function getQueryTime()
    {
        return $this->queryTime;
    }

    /**
     * Returns main select
     *
     * @param string $alias
     * @return Select
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getMainSelect($alias = 'main')
    {
        $select = $this->getConnection()->select();
        $select->from([$alias => $this->getMainTable()], []);
        return $select;
    }

    /**
     * Joins attribute value to a select
     *
     * @param Select $select
     * @param object $attribute
     * @param string $tableAlias
     * @return $this
     */
    public function joinAttribute(Select $select, $attribute, $tableAlias = 'main')
    {
        $defaultAlias = sprintf('attribute_%s_default', $attribute->code);
        $scopeAlias = sprintf('attribute_%s_scope', $attribute->code);
        $attributeTable = $this->getTable(['entity', $attribute->type]);

        if (1 - $attribute->required > 0.01) {
            $select->joinLeft(
                [$defaultAlias => $attributeTable],
                $this->andCondition([
                    sprintf('%s.entity_id = %s.entity_id', $defaultAlias, $tableAlias),
                    sprintf('%s.attribute_id = ?', $defaultAlias) => $attribute->id,
                    sprintf('%s.scope_id = ?', $defaultAlias) => 0
                ]),
                []
            );
        } else {
            $select->join(
                [$defaultAlias => $attributeTable],
                $this->andCondition([
                    sprintf('%s.entity_id = %s.entity_id', $defaultAlias, $tableAlias),
                    sprintf('%s.attribute_id = ?', $defaultAlias) => $attribute->id,
                    sprintf('%s.scope_id = ?', $defaultAlias) => 0
                ]),
                []
            );
        }

        if ($attribute->scope && $this->scopeCode) {
            $select->joinLeft(
                [$scopeAlias => $attributeTable],
                $this->andCondition([
                    sprintf('%s.entity_id = %s.entity_id', $defaultAlias, $scopeAlias),
                    sprintf('%s.attribute_id = %s.attribute_id', $defaultAlias, $scopeAlias),
                    sprintf('%s.scope_id = ?', $scopeAlias) => $this->scope->getId($this->scopeCode)
                ]),
                []
            );

            $valueExpression = sprintf(
                'IF(%2$s.value_id IS NOT NULL, %2$s.value, %1$s.value)',
                $defaultAlias,
                $scopeAlias
            );

        } else {
            $valueExpression = sprintf('%s.value', $defaultAlias);
        }

        $select->columns([
            $attribute->code => $valueExpression
        ]);

        return $valueExpression;
    }

    /**
     * List of conditions to join
     *
     * @param string[]|mixed[] $conditions
     * @return string
     */
    public function andCondition($conditions)
    {
        $and = [];
        foreach ($conditions as $condition => $placeholder) {
            if (!is_int($condition)) {
                $and[] = $this->getConnection()->quoteInto($condition, $placeholder);
                continue;
            }

            $and[] = $placeholder;
        }

        return implode(' AND ', $and);
    }

    /**
     * Returns select for retrieving attribute information
     *
     * @param $type
     * @param string $alias
     * @param bool $ignoreScope
     * @param null|int|int[] $attributeId
     * @return Select
     */
    public function getAttributeSelect($type, $alias = 'attribute', $attributeId = null, $ignoreScope = false)
    {
        $select = $this->getConnection()->select();
        $select
            ->from(
                [$alias => $this->getTable(['entity', $type])],
                []
            );

        $select->columns([
            'entity_id',
            'attribute_id',
            'value',
        ], $alias);

        if ($this->scopeCode && !$ignoreScope) {
            $select->where(sprintf('%s.scope_id IN(?)', $alias), [0, $this->scope->getId($this->scopeCode)]);
            $select->columns([
                'scope_id',
            ], $alias);
        }

        if (isset($attributeId) && is_array($attributeId)) {
            $select->where(sprintf('%s.attribute_id IN(?)', $alias), $attributeId);
        } elseif (isset($attributeId)) {
            $select->where(sprintf('%s.attribute_id = ?', $alias), $attributeId);
        }

        return $select;
    }

    protected function configureBoundarySelect(Select $select)
    {
        return $this;
    }

    public function limitFlatActive(Select $select, $alias = 'main')
    {
        $select->join(['flat' => $this->getTable('entity_flat')], $alias . '.entity_id = flat.entity_id', []);
        $select->where('flat.scope_id = ?', $this->scope->getId($this->scopeCode));
        $select->where('flat.is_active = ?', 1);
        return $this;
    }

    /**
     * Validates database
     *
     * @param string $databaseName
     * @return $this
     */
    public function validateDatabase($databaseName)
    {
        $row = $this->getConnection()->fetchOne(
            'show databases like :database', ['database' => $databaseName]
        );

        if (!$row) {
            throw new \RuntimeException(sprintf('Database %s is not created', $databaseName));
        }

        $this->getConnection()->query(sprintf('USE %s', $this->getConnection()->quoteIdentifier($databaseName)));
        return $this;
    }

    public function reset()
    {
        $this->queryTime = 0;
        return $this;
    }

    /**
     * @param Select|string $query
     * @param array $bind
     * @return \Zend_Db_Statement_Interface
     */
    public function profiledQuery($query, array $bind = [])
    {
        $this->queries[$this->queryCode][] = (string)$query;

        $startTime = microtime(true);
        $result = $this->getConnection()->query($query, $bind);
        $this->queryTime += microtime(true) - $startTime;
        return $result;
    }

    /**
     * Requested attribute codes
     *
     * @return string[]
     */
    public function getAttributeCodes()
    {
        return $this->attributeCodes;
    }

    /**
     * Returns a limit sample
     *
     * @param mixed[] $additionalArgs
     * @return \Closure
     */
    protected function getLimitSample($additionalArgs = [])
    {
        return function ($size, $maximumBoundary, $runCount) use ($additionalArgs) {
            $step = 0;
            $samples = [];
            while ($runCount > $step && $maximumBoundary > ($size * $step)) {
                $samples[] = array_merge([$size * $step, $size], $additionalArgs);
                $step ++;
            }

            return $samples;
        };
    }

    public function setAttributeCodes(array $code)
    {
        $this->attributeCodes = array_combine($code, $code);
        return $this;
    }

    /**
     * Sets scope code
     *
     * @param string $code
     * @return $this
     */
    public function setScopeCode($code)
    {
        $this->scopeCode = $code;
        return $this;
    }


    public function getQueries()
    {
        return $this->queries;
    }

    public function select()
    {
        return new \EcomDev\MagentoPerformance\ResourceModel\Select(
            $this->getConnection()
        );
    }

    public function setup()
    {
        $this->getConnection()->query('SET SESSION query_cache_type = OFF');
        return $this;
    }

    public function cleanup()
    {
        return $this;
    }

    public function setOption($name, $value)
    {
        $this->option[$name] = $value;
        return $this;
    }

    /**
     * Returns an option
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getOption($name, $default = null)
    {
        if (!isset($this->option[$name])) {
            return $default;
        }

        return $this->option[$name];
    }

    /**
     * @param string $queryCode
     * @return $this
     */
    public function setQueryCode($queryCode)
    {
        $this->queryCode = $queryCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getQueryCode()
    {
        return $this->queryCode;
    }
    
    
    

}
