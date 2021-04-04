<?php

namespace Bjerke\Bread\Traits;

use Bjerke\Bread\Builder\DefinitionBuilder;
use UnexpectedValueException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait FieldDefinition
{
    protected static bool $isCompilingDefinition = false;
    protected array $definition = [];
    protected array $flatFieldDefinition = [];

    /**
     * Sets the definition on model
     *
     * @param bool $force
     */
    public function compileDefinition($force = false): void
    {
        // Avoid max nesting
        if (
            !$force &&
            (self::$isCompilingDefinition || $this->isDefined())
        ) {
            return;
        }

        if (!$force) {
            $cachedDefinition = $this->getCachedDefinition();
            if (!empty($cachedDefinition)) {
                $this->setFieldDefinition($cachedDefinition['definition'], $cachedDefinition['flatDefinition']);
                return;
            }
        }

        self::$isCompilingDefinition = true;

        assert($this instanceof Model);
        $builder = $this->define(new DefinitionBuilder($this));
        if (!$builder instanceof DefinitionBuilder) {
            throw new UnexpectedValueException('Return value of define must be an instance of DefinitionBuilder');
        }

        self::$isCompilingDefinition = false;

        $fullDefinition = $builder->getFullDefinition();
        $flatDefinition = $builder->getFlatDefinition();

        if ($builder->isCachable()) {
            Cache::tags(['bread.model_definitions'])->put(
                $this->getDefinitionCacheKey(),
                [
                    'definition' => $fullDefinition,
                    'flatDefinition' => $flatDefinition
                ],
                Carbon::now()->addMinutes($builder->getCacheTTL())
            );
        }

        $this->setFieldDefinition($fullDefinition, $flatDefinition);
    }

    private function getDefinitionCacheKey(): string
    {
        return strtolower(class_basename($this));
    }

    private function getCachedDefinition(): array
    {
        $cacheKey = $this->getDefinitionCacheKey();

        if (Cache::supportsTags() && Cache::tags(['bread.model_definitions'])->has($cacheKey)) {
            $cachedDefinition = Cache::tags(['bread.model_definitions'])->get($cacheKey);
            if (
                isset($cachedDefinition['definition'], $cachedDefinition['flatDefinition']) &&
                !empty($cachedDefinition['definition']) && !empty($cachedDefinition['flatDefinition'])
            ) {
                return $cachedDefinition;
            }
        }

        return [];
    }

    /**
     * Checks if current model instance has a compiled field definition attached
     */
    public function isDefined(): bool
    {
        return !empty($this->definition) && !empty($this->flatFieldDefinition);
    }

    /**
     * Defines the current model with fields for API
     *
     * @param DefinitionBuilder $definition
     *
     * @return DefinitionBuilder
     */
    protected function define(DefinitionBuilder $definition): DefinitionBuilder
    {
        return $definition;
    }

    /**
     * Sets options array on definition
     *
     * @param array $options
     */
    protected function setOptions(array $options): void
    {
        $this->definition['options'] = $options;
    }

    /**
     * Retrieve this models' field definition
     */
    public function getFieldDefinition(): array
    {
        if ($this->isDefined()) {
            return $this->definition;
        }

        $this->compileDefinition();
        return $this->definition;
    }

    /**
     * Retrieve this models flat field definition
     */
    public function getFlatFieldDefinition(): array
    {
        if (empty($this->flatFieldDefinition)) {
            $this->compileDefinition();
        }

        return $this->flatFieldDefinition;
    }

    /**
     * Returns remote relation fields (hasMany, ManyToMany etc)
     *
     * @return array
     */
    public function getRemoteRelationFields(): array
    {
        $flatDefinition = $this->getFlatFieldDefinition();

        return array_filter($flatDefinition, static function ($field) {
            return \in_array($field['type'], ['HASMANY', 'MANYTOMANY']);
        });
    }

    /**
     * Manually set the definition of the model
     *
     * @param array $definition
     * @param array $flatDefinition
     */
    public function setFieldDefinition(array $definition, array $flatDefinition): void
    {
        if (empty($definition)) {
            $definition = [
                'model_info' => [],
                'field_groups' => [],
                'guards' => [],
                'rules' => []
            ];
        }

        $this->setFieldGuards($definition);
        $this->setFieldRules($definition);

        $this->flatFieldDefinition = $flatDefinition;
        $this->definition = $definition;
    }

    public function setFieldGuards(array $definition): void
    {
        if (!isset($definition['guards']) || empty($definition['guards'])) {
            return;
        }

        foreach ($definition['guards'] as $guardType => $fields) {
            if (empty($fields)) {
                continue;
            }

            foreach ($fields as $field) {
                if (!in_array($field, $this->{$guardType}, true)) {
                    $this->{$guardType}[] = $field;
                }
            }
        }
    }

    public function setFieldRules(array $definition): void
    {
        if (isset($definition['rules']) && !empty($definition['rules'])) {
            $this->rules = array_replace($definition['rules'], $this->rules);
        }
    }

    /**
     * Get default values for fields
     *
     * @param null|string $fieldName Optionally get default value for a specific field, null if all
     *
     * @return array|mixed|null
     */
    public function getFieldDefaults($fieldName = null): ?array
    {
        if (empty($this->flatFieldDefinition)) {
            $this->compileDefinition();
        }

        if (!$fieldName) {
            //Return all
            $defaults = [];

            foreach ($this->flatFieldDefinition as $key => $definition) {
                $defaults[$key] = $definition['default'];
            }

            return $defaults;
        }

        //Return default for specific field
        if (isset($this->flatFieldDefinition[$fieldName])) {
            return $this->flatFieldDefinition[$fieldName]['default'];
        }

        return null;
    }

    /**
     * Returns array with relation type as string
     * (BelongsToMany, HasMany, BelongsTo etc), related model and relation method name
     *
     * @param string $relationName
     *
     * @return array
     */
    public function getRelationType(string $relationName): array
    {
        $method = $relationName;

        if (!method_exists($this, $relationName)) {
            $method = Str::plural($relationName);
        }

        $relation = $this->{$method}();

        return [
            'method' => $method,
            'model' => class_basename($relation->getModel()),
            'type'   => class_basename($relation)
        ];
    }
}
