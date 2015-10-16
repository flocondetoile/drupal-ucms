<?php

namespace Ucms\Search\Mapping;

class IntegerType implements TypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function convert($value)
    {
        return (int)$value;
    }
}
