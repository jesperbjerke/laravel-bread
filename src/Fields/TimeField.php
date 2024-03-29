<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a time field to the model (time input)
 */
class TimeField extends BaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('TEXT');
        $this->inputType('time');
        $this->addValidation('date_format:H:i:s');
        $this->addExtraData([
            'timezone' => 'UTC'
        ]);

        return $this;
    }

    /**
     * @param string $time Maximum time allowed (H:i:s)
     *
     * @return $this
     */
    public function maxDate(string $time): static
    {
        $this->addExtraData([
            'max' => $time
        ]);
        return $this;
    }

    /**
     * @param string $time Minimum time allowed (H:i:s)
     *
     * @return $this
     */
    public function minDate(string $time): static
    {
        $this->addExtraData([
            'min' => $time
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
