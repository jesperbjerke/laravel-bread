<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a wysiwyg field to the model (supposed to allow some kind of editor)
 */
class WysiwygField extends BaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('JSON');
        $this->inputType('wysiwyg');

        return $this;
    }
}
