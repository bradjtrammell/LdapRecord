<?php

namespace LdapRecord\Query;

use LdapRecord\Models\Model;
use Tightenco\Collect\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    /**
     * {@inheritdoc}
     */
    protected function valueRetriever($value)
    {
        if ($this->useAsCallable($value)) {
            return $value;
        }

        return function ($item) use ($value) {
            if ($item instanceof Model) {
                return $item->getFirstAttribute($value);
            }

            return data_get($item, $value);
        };
    }
}
