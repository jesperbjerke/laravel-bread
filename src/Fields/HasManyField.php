<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\RelationBaseField;

/**
 * Add a HasMany relation field to the model
 */
class HasManyField extends RelationBaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('HASMANY');
        $this->inputType('relation-list');
        $this->addValidation('array');
        $this->fillable(false);

        if (array_key_exists('relations', config('bread.default_field_groups'))) {
            $this->group('relations');
        }

        return $this;
    }
}
