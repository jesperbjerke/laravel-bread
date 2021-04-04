<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a boolean field to the model (checkbox)
 */
class BoolField extends BaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('BOOL');
        $this->inputType('boolean');
        $this->addValidation('boolean');
        $this->showLabel(false);
        return $this;
    }

    /**
     * @param string $format checkbox|toggle
     */
    public function stylingFormat(string $format = 'checkbox'): self
    {
        $this->addExtraData([
            'styling_format' => $format
        ]);

        return $this;
    }

}
