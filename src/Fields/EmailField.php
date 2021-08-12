<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add an email field to the model (email input)
 */
class EmailField extends BaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('TEXT');
        $this->inputType('email');
        $this->addValidation('email');

        return $this;
    }
}
