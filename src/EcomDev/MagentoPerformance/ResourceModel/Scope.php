<?php

namespace EcomDev\MagentoPerformance\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Scope extends AbstractDb
{
    private $scopeMap;

    protected function _construct()
    {
        $this->_init('scope', 'scope_id');
    }

    public function getId($code)
    {
        $this->initMap();
        return $this->scopeMap['id'][$code];
    }

    public function getCode($id)
    {
        $this->initMap();
        return $this->scopeMap['code'][$id];
    }

    public function getLocale($code)
    {
        $this->initMap();
        return $this->scopeMap['locale'][$code];
    }

    /**
     * Returns codes
     *
     * @return string[]
     */
    public function getCodes()
    {
        $this->initMap();
        return array_keys($this->scopeMap['id']);
    }

    private function initMap()
    {
        if ($this->scopeMap === null) {
            $this->scopeMap['id'] = [];
            $this->scopeMap['code'] = [];
            $this->scopeMap['locale'] = [];

            $select = $this->getConnection()->select()
                ->from($this->getMainTable(), ['scope_id', 'code', 'locale'])
                ->where('scope_id > ?', 0);

            foreach ($select->query() as $scope) {
                $this->scopeMap['id'][$scope['code']] = $scope['scope_id'];
                $this->scopeMap['code'][$scope['scope_id']] = $scope['code'];
                $this->scopeMap['locale'][$scope['code']] = $scope['locale'];
            }
        }

        return $this;
    }

    public function reset()
    {
        $this->scopeMap = null;
        return $this;
    }

}
