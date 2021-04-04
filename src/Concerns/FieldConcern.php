<?php

namespace Bjerke\Bread\Concerns;

interface FieldConcern
{
    public function name(string $name): self;

    public function label(string $label): self;
    public function showLabel(bool $show = true): self;

    public function description(string $description): self;
    public function placeholder(string $placeholder): self;

    public function group(string $group): self;
    public function getGroup(): string;

    public function defaultValue($value = null): self;

    public function required(bool $required = false, ?string $requiredRule = null): self;

    public function setValidationKey(string $key): self;
    public function getValidationKey(): string;

    public function setValidation(array $rules): self;
    public function addValidation(string $rule): self;

    public function getRuleString(): string;
    public function getRules(): array;

    public function fillable(bool $fillable = true): self;
    public function hidden(bool $hidden = true): self;
    public function inputHidden(bool $hidden = true): self;

    public function type(string $type): self;
    public function inputType(string $type): self;

    public function addExtraData(array $data): self;

    public function hiddenOn(array $hiddenOn): self;

    public function getDefinition(): array;
}
