<?php

namespace Bjerke\Bread\Fields\Base;

use Bjerke\Bread\Builder\DefinitionBuilder;
use Closure;

abstract class NestedBaseField extends BaseField
{
    protected array $nestedRules = [];
    protected array $nestedFields = [];
    protected bool $useNestedGroups = false;
    protected bool $isRepeatable = false;

    public function fields(Closure $callback, bool $useNestedGroups = false): self
    {
        $this->useNestedGroups = $useNestedGroups;

        $callback($builder = new DefinitionBuilder());
        $definition = $builder->getFullDefinition();

        $this->nestedRules = $definition['rules'];

        if ($useNestedGroups) {
            $this->nestedFields = $definition['field_groups'];
        } else {
            $groupFields = array_map(static fn ($group) => $group['fields'], $definition['field_groups']);
            $this->nestedFields = array_merge(...$groupFields);
        }

        return $this;
    }

    public function getFields(): array
    {
        return $this->nestedFields;
    }

    public function getRules(): array
    {
        $rules = [];
        $parentName = $this->getValidationKey();

        foreach ($this->nestedRules as $key => $rule) {
            $validationKey = ($this->isRepeatable) ? ($parentName . '.*.' . $key) : ($parentName . '.' . $key);
            $rules[$validationKey] = $rule;
        }

        return $rules;
    }
}
