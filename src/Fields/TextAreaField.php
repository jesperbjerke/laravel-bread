<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a textarea field to the model (textarea input)
 */
class TextAreaField extends BaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('TEXT');
        $this->inputType('textarea');
        $this->addValidation('string');

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
