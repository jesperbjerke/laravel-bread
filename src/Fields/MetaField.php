<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\NestedBaseField;
use Closure;

/**
 * Adds a meta relation to add nested fields to
 */
class MetaField extends NestedBaseField
{
    public function __construct(string $relationName, ?string $name = null)
    {
        parent::__construct($name);
        $this->relationName($relationName);
    }

    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('META');
        $this->inputType('nested-fields');
        $this->fillable(false);

        return $this;
    }

    public function relationName(string $relationName): self
    {
        $this->addExtraData([
            'relation' => $relationName
        ]);

        return $this;
    }

    public function fields(Closure $callback, bool $useNestedGroups = false): self
    {
        parent::fields($callback, $useNestedGroups);
        $this->addExtraData([
            'fields' => $this->getFields()
        ]);

        return $this;
    }
}
