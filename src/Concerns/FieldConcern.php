<?php

namespace Bjerke\Bread\Concerns;

interface FieldConcern
{
    public function name(string $name): static;

    public function label(string $label): static;
    public function showLabel(bool $show = true): static;

    public function description(string $description): static;
    public function placeholder(string $placeholder): static;

    public function group(string $group): static;
    public function getGroup(): string;

    public function defaultValue($value = null): static;

    public function required(bool $required = false, ?string $requiredRule = null): static;

    public function setValidationKey(string $key): static;
    public function getValidationKey(): string;

    public function setValidation(array $rules): static;
    public function addValidation(string $rule): static;

    public function getRuleString(): string;
    public function getRules(): array;

    public function fillable(bool $fillable = true): static;
    public function hidden(bool $hidden = true): static;
    public function inputHidden(bool $hidden = true): static;

    public function type(string $type): static;
    public function inputType(string $type): static;

    public function addExtraData(array $data): static;

    public function hiddenOn(array $hiddenOn): static;

    public function getDefinition(): array;
}
