<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a float field to the model (number input)
 */
class FloatField extends BaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('FLOAT');
        $this->inputType('number');
        $this->addValidation('numeric');
        return $this;
    }
}
