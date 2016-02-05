<?php

namespace EcomDev\MagentoPerformance\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Attribute extends AbstractDb
{
    /**
     * @var object[]
     */
    private $attributeByCode;

    /**
     * @var object[]
     */
    private $scopeAwareAttribute;

    /**
     * @var object[][]
     */
    private $attributeByType;

    protected function _construct()
    {
        $this->_init('attribute', 'attribute_id');
    }

    /**
     * Resets data
     *
     * @return $this
     */
    public function reset()
    {
        $this->attributeByCode = null;
        $this->attributeByType = null;
        $this->scopeAwareAttribute = null;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getAll()
    {
        $this->initAttribute();
        return $this->attributeByCode;
    }

    /**
     * @return string[]
     */
    public function getAllScopeAware()
    {
        $this->initAttribute();
        return $this->scopeAwareAttribute;
    }


    /**
     * Returns list of attributes by type
     *
     * @return string[][]
     */
    public function getAllByType()
    {
        $this->initAttribute();
        return$this->attributeByType;
    }

    /**
     * Return init attribute
     *
     * @return $this
     */
    private function initAttribute()
    {
        if ($this->attributeByCode === null) {
            $this->attributeByCode = [];
            $this->attributeByType = [];
            $this->scopeAwareAttribute = [];

            $select = $this->getConnection()->select()
                ->from($this->getMainTable(), '*');

            foreach ($select->query() as $row) {
                $generator = explode(':', $row['generator']);
                $row['generatorMethod'] = array_shift($generator);
                $row['generatorArguments'] = $generator;
                $row['id'] = $row['attribute_id'];
                $this->attributeByCode[$row['code']] = (object)$row;
                $this->attributeByType[$row['type']][$row['id']] = $this->attributeByCode[$row['code']];
                if ($row['scope']) {
                    $this->scopeAwareAttribute[$row['code']] = $this->attributeByCode[$row['code']];
                }
            }
        }

        return $this;
    }

}
