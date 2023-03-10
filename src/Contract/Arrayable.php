<?php

namespace NaturalCloud\Logger\Contract;

interface Arrayable
{
    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray();
}
