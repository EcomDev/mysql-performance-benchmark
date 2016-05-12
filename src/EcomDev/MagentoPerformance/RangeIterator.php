<?php

namespace EcomDev\MagentoPerformance;

class RangeIterator implements \Iterator
{
    private $min;
    private $max;
    private $step;
    private $offset;

    /**
     * RangeIterator constructor.
     *
     * @param int $min
     * @param int $max
     * @param int $step
     */
    public function __construct($min, $max, $step)
    {
        $this->min = $min;
        $this->max = $max;
        $this->step = $step;
    }

    public function current()
    {
        return min($this->offset + $this->step, $this->max + 1);
    }

    public function next()
    {
        $this->offset += $this->step;
    }

    public function key()
    {
        return $this->offset;
    }

    public function valid()
    {
        return $this->offset <= max($this->step, ($this->max - $this->step) + 1);
    }

    public function rewind()
    {
        $this->offset = $this->min;
    }

}
