<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;
use Illuminate\Validation\Rule;

/**
 * Add a checkbox select to the model (checkbox inputs)
 * optionally add styling settings
 */
class CheckboxField extends BaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('ENUM');
        $this->inputType('checkbox');

        return $this;
    }

    public function name(string $name): self
    {
        $hasValidationKey = (bool) $this->validationKey;
        parent::name($name);

        if (!$hasValidationKey) {
            $this->setValidationKey($name . '.*');
        }

        return $this;
    }

    /**
     * Add options to the select
     *
     * @param array $options Associative array where key is the value of the option,
     *                       and the value is the label of the option
     *
     * @return $this
     */
    public function options(array $options = []): self
    {
        $this->addValidation((string) Rule::in(array_keys($options)));
        $this->addExtraData([
            'options' => $options
        ]);

        return $this;
    }

    /**
     * @param string $format checkbox
     */
    public function stylingFormat(string $format = 'checkbox'): self
    {
        $this->addExtraData([
            'styling_format' => $format
        ]);

        return $this;
    }
}
