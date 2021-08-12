<?php

namespace Bjerke\Bread\Fields\Base;

use Bjerke\Bread\Concerns\FieldConcern;

abstract class BaseField implements FieldConcern
{
    protected array $definition = [];
    protected string $requiredRule = 'nullable';
    protected ?string $validationKey = null;
    protected array $validationRules = [];
    protected ?string $group = null;

    public function __construct(?string $name = null)
    {
        $this->setDefaultDefinition();

        if ($name) {
            $this->name($name);
        }
    }

    protected function setDefaultDefinition(): static
    {
        $this->definition = [
            'label' => null,
            'description' => null,
            'placeholder' => null,
            'default' => null,
            'required' => false,
            'show_label' => true,
            'hidden' => false,
            'hidden_on' => [],
            'fillable' => true,
            'extra_data' => []
        ];
        return $this;
    }

    public function name(string $name): static
    {
        $this->definition['name'] = $name;

        if (!$this->validationKey) {
            return $this->setValidationKey($name);
        }

        return $this;
    }

    public function label(string $label): static
    {
        $this->definition['label'] = $label;
        return $this;
    }

    public function showLabel(bool $show = true): static
    {
        $this->definition['show_label'] = $show;
        return $this;
    }

    public function description(string $description): static
    {
        $this->definition['description'] = $description;
        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->definition['placeholder'] = $placeholder;
        return $this;
    }

    public function defaultValue($value = null): static
    {
        $this->definition['default'] = $value;
        return $this;
    }

    public function required(bool $required = false, ?string $requiredRule = null): static
    {
        $this->definition['required'] = $required;
        $this->requiredRule = $requiredRule ?? (($required) ? 'required' : 'nullable');
        return $this;
    }

    public function setValidation(array $rules): static
    {
        $this->validationRules = $rules;
        return $this;
    }

    public function addValidation(string $rule): static
    {
        $this->validationRules[] = $rule;
        return $this;
    }

    public function getRuleString(): string
    {
        return implode('|', [$this->requiredRule, ...$this->validationRules]);
    }

    public function getRules(): array
    {
        return [];
    }

    public function setValidationKey(string $key): static
    {
        $this->validationKey = $key;
        return $this;
    }

    public function getValidationKey(): string
    {
        return $this->validationKey;
    }

    public function fillable(bool $fillable = true): static
    {
        $this->definition['fillable'] = $fillable;
        return $this;
    }

    public function hidden(bool $hidden = true): static
    {
        $this->definition['hidden'] = $hidden;
        return $this;
    }

    public function inputHidden(bool $hidden = true): static
    {
        $this->definition['input_hidden'] = $hidden;
        return $this;
    }

    public function type(string $type): static
    {
        $this->definition['type'] = $type;
        return $this;
    }

    public function inputType(string $type): static
    {
        $this->definition['input_type'] = $type;
        return $this;
    }

    public function addExtraData(array $data): static
    {
        $this->definition['extra_data'] = array_replace_recursive($this->definition['extra_data'], $data);
        return $this;
    }

    public function group(string $group): static
    {
        $this->group = $group;
        return $this;
    }

    public function getGroup(): string
    {
        return $this->group ?? config('bread.default_field_group');
    }

    public function hiddenOn(array $hiddenOn): static
    {
        $this->definition['hidden_on'] = $hiddenOn;
        return $this;
    }

    public function getDefinition(): array
    {
        $this->definition['rule_string'] = $this->getRuleString();
        return $this->definition;
    }
}
