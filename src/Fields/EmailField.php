<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add an email field to the model (email input)
 */
class EmailField extends BaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('TEXT');
        $this->inputType('email');
        $this->addValidation('email');

        return $this;
    }
}
