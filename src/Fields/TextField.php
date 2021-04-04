<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a text field to the model (text input)
 */
class TextField extends BaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('TEXT');
        $this->inputType('text');
        $this->addValidation('string');
        $this->maxLength();

        return $this;
    }

    public function maxLength(int $length = 255): self
    {
        $this->validationRules = array_filter(
            $this->validationRules,
            static fn ($rule) => strpos($rule, 'max:') === false
        );
        $this->addValidation('max:' . 255);
        $this->addExtraData([
            'max' => $length
        ]);
        return $this;
    }

    public function minLength(int $length = 255): self
    {
        $this->validationRules = array_filter(
            $this->validationRules,
            static fn ($rule) => strpos($rule, 'min:') === false
        );
        $this->addValidation('min:' . 255);
        $this->addExtraData([
            'min' => $length
        ]);
        return $this;
    }
}
