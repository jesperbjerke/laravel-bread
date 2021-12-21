<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add an integer field to the model (number input)
 */
class IntField extends BaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('INT');
        $this->inputType('number');
        $this->addValidation('numeric|integer');
        return $this;
    }
}
