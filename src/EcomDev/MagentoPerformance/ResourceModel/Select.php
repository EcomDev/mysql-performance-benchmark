<?php

namespace EcomDev\MagentoPerformance\ResourceModel;

class Select extends \Magento\Framework\DB\Select
{
    /**
     * Outfile flag
     *
     * @var bool
     */
    private $outfile = false;

    /**
     * Make it possible to add INTO OUTFILE
     *
     * @param string $sql
     * @return null|string
     */
    protected function _renderColumns($sql)
    {
        $sql .= ' SQL_NO_CACHE ';
        $columns = parent::_renderColumns($sql);
        if ($this->outfile) {
            $columns .= sprintf(
                ' INTO OUTFILE %s',
                $this->getAdapter()->quote($this->outfile)
            );
        }

        return $columns;
    }

    
    /**
     * Resets select object
     *
     * @param null $part
     * @return $this
     */
    public function reset($part = null)
    {
        if ($part === self::COLUMNS || $part === null) {
            $this->outfile = false;
        }

        return parent::reset($part);
    }

    /**
     * Makes it possible to make select into outfile
     *
     * Enabled concurrent data processing
     *
     * @param $file
     * @return $this
     */
    public function intoOutfile($file)
    {
        $this->outfile = $file;
        return $this;
    }

}
