<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\RelationBaseField;

/**
 * Add a ManyToMany relation field to the model
 */
class ManyToManyField extends RelationBaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('MANYTOMANY');
        $this->inputType('relation-list');
        $this->addValidation('array');
        $this->fillable(false);

        if (array_key_exists('relations', config('bread.default_field_group'))) {
            $this->group('relations');
        }

        return $this;
    }
}
