<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a text field to the model (text input)
 */
class TextField extends BaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('TEXT');
        $this->inputType('text');
        $this->addValidation('string');
        $this->maxLength();

        return $this;
    }

    public function maxLength(int $length = 255): static
    {
        $this->validationRules = array_filter(
            $this->validationRules,
            static fn ($rule) => strpos($rule, 'max:') === false
        );
        $this->addValidation('max:' . $length);
        $this->addExtraData([
            'max' => $length
        ]);
        return $this;
    }

    public function minLength(int $length = 255): static
    {
        $this->validationRules = array_filter(
            $this->validationRules,
            static fn ($rule) => strpos($rule, 'min:') === false
        );
        $this->addValidation('min:' . $length);
        $this->addExtraData([
            'min' => $length
        ]);
        return $this;
    }
}
