<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a float field to the model (number input)
 */
class FloatField extends BaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('FLOAT');
        $this->inputType('number');
        $this->addValidation('numeric');
        return $this;
    }

    public function max(int $length): static
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

    public function min(int $length): static
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
