<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a datetime field to the model (date input)
 */
class DateTimeField extends BaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('TIMESTAMP');
        $this->inputType('datetime');
        $this->addValidation('date');
        $this->addExtraData([
            'timezone' => 'UTC'
        ]);

        return $this;
    }

    /**
     * @param string $date Maximum date allowed (Y-m-d H:i:s)
     *
     * @return $this
     */
    public function maxDate(string $date): static
    {
        $this->addValidation('before_or_equal:' . $date);
        $this->addExtraData([
            'max' => $date
        ]);
        return $this;
    }

    /**
     * @param string $date Minimum date allowed (Y-m-d H:i:s)
     *
     * @return $this
     */
    public function minDate(string $date): static
    {
        $this->addValidation('after_or_equal:' . $date);
        $this->addExtraData([
            'min' => $date
        ]);
        return $this;
    }

    public function timezone(string $timezone): static
    {
        $this->addExtraData([
            'timezone' => $timezone
        ]);
        return $this;
    }
}
