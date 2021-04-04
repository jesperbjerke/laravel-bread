<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a password confirm field to the model (password input)
 */
class PasswordConfirmField extends BaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('TEXT');
        $this->inputType('password-confirmation');
        $this->fillable(false);
        $this->hiddenOn(['view']);
        return $this;
    }

    public function matchField(string $field): self
    {
        $this->required(true, 'required_with:' . $field);
        $this->addValidation('same:' . $field);
        $this->addExtraData([
            'match_field' => $field
        ]);
        return $this;
    }
}
