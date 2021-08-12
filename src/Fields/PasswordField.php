<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a password field to the model (password input)
 */
class PasswordField extends BaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('TEXT');
        $this->inputType('password');
        $this->addValidation('string');
        $this->hiddenOn(['view']);
        return $this;
    }
}
