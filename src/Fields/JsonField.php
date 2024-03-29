<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\NestedBaseField;
use Closure;

/**
 * Adds a nested JSON field
 */
class JsonField extends NestedBaseField
{
    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('JSON');
        $this->inputType('nested-fields');

        return $this;
    }

    public function fields(Closure $callback, bool $useNestedGroups = false): static
    {
        parent::fields($callback, $useNestedGroups);
        $this->addExtraData([
            'fields' => $this->getFields()
        ]);

        return $this;
    }
}
