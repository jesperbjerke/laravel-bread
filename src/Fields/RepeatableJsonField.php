<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\NestedBaseField;
use Closure;

/**
 * Adds a repeatable nested JSON field,
 * declared fields will be appended to its own row in an array
 */
class RepeatableJsonField extends NestedBaseField
{
    protected bool $isRepeatable = true;

    protected function setDefaultDefinition(): static
    {
        parent::setDefaultDefinition();
        $this->type('JSON');
        $this->inputType('repeatable-nested-fields');

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
