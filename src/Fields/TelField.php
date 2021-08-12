<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a phone number field to the model (tel input)
 */
class TelField extends BaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('TEXT');
        $this->inputType('tel');
        $this->addValidation('string|regex:/^\+?[1-9]\d{1,14}$/');

        return $this;
    }
}
