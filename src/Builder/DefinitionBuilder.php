<?php

namespace Bjerke\Bread\Builder;

use Bjerke\Bread\Concerns\FieldConcern;
use Bjerke\Bread\Exceptions\InvalidFieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use BadMethodCallException;

class DefinitionBuilder
{
    protected bool $cacheable = false;
    protected int $cacheTTL = 20160;
    protected array $groups = [];
    protected array $rules = [];
    protected array $fields = [];
    protected array $definitions = [];
    protected array $options = [];
    protected array $guards = [
        'fillable' => [],
        'hidden' => []
    ];
    protected array $modelInfo = [
        'name' => null,
        'plural_name' => null
    ];

    public function __construct(?Model $model = null)
    {
        $this->groups = config('bread.default_field_groups');
        if ($model) {
            $this->setModelInfo($model);
        }
    }

    public function setModelInfo(Model $model): void
    {
        $baseName = class_basename($model);
        $modelName = Str::lower($baseName);
        $this->modelInfo['name'] = (Lang::has('models.' . $modelName . '.singular')) ?
            Lang::get('models.' . $modelName . '.singular') :
            $baseName;
        $this->modelInfo['plural_name'] = (Lang::has('models.' . $modelName . '.plural')) ?
            Lang::get('models.' . $modelName . '.plural') :
            Str::plural($baseName);
    }

    /**
     * Add a new field group
     *
     * @param string $key
     * @param string $label
     * @param string $description
     * @param false $tabbed Enables/disables tabbed interface, provide key of tab-group as string to enable
     * @param int|null $order
     */
    public function addGroup(
        string $key,
        string $label,
        string $description,
        $tabbed = false,
        ?int $order = null
    ): void {
        $this->groups[$key] = [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'tabbed' => $tabbed,
            'order' => $order ?? count($this->groups),
            'fields' => []
        ];
    }

    /**
     * Add a new field to this definition
     *
     * @param FieldConcern $field
     *
     * @return $this
     * @throws InvalidFieldDefinition
     */
    public function addField(FieldConcern $field): self
    {
        $definition = $field->getDefinition();
        $this->validateSingleDefinition($definition);

        $hidden = $definition['hidden'] ?? false;
        if ($hidden && !in_array($definition['name'], $this->guards['hidden'], true)) {
            $this->guards['hidden'][] = $definition['name'];
        }

        if ($definition['input_hidden'] ?? false) {
            $definition['hidden'] = true;
            unset($definition['input_hidden']);
        }

        $fillable = $definition['fillable'] ?? true;
        if ($fillable && !in_array($definition['name'], $this->guards['fillable'], true)) {
            $this->guards['fillable'][] = $definition['name'];
        }

        $arrayedRules = $field->getRules();
        if (!empty($arrayedRules)) {
            foreach ($arrayedRules as $key => $rule) {
                $this->rules[$key] = $rule;
            }
        }

        if ($definition['rule_string'] ?: false) {
            $this->rules[$field->getValidationKey()] = $definition['rule_string'];
        }

        $group = $field->getGroup();
        if (!isset($this->groups[$group])) {
            throw new InvalidFieldDefinition($definition['name'], "Group key $group not configured");
        }

        $this->fields[$definition['name']] = $field;
        $this->definitions[$definition['name']] = $definition;

        return $this;
    }

    public function addFields(array $fields): self
    {
        foreach ($fields as $field) {
            $this->addField($field);
        }

        return $this;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * Validated a definition for a single field
     *
     * @param $definition
     *
     * @throws InvalidFieldDefinition
     */
    protected function validateSingleDefinition($definition): void
    {
        $requiredFields = [
            'name',
            'type',
            'input_type'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($definition[$field])) {
                throw new InvalidFieldDefinition(self::class, "$field is required");
            }
        }

        $keysMustExist = [
            'label',
            'description',
            'placeholder',
            'default',
            'required',
            'rule_string',
            'show_label',
            'hidden',
            'hidden_on',
            'fillable',
            'extra_data'
        ];

        if (!empty($missingKeys = array_diff($keysMustExist, array_keys($definition)))) {
            $missingKeys = implode(', ', $missingKeys);
            throw new InvalidFieldDefinition(self::class, "$missingKeys are missing from the definition");
        }
    }

    public function getFlatDefinition(): array
    {
        return $this->definitions;
    }

    public function getFullDefinition(): array
    {
        $definition = [
            'model_info' => $this->modelInfo,
            'guards' => $this->guards,
            'rules' => $this->rules,
            'field_groups' => $this->groups,
            'options' => $this->options
        ];

        foreach ($this->fields as $fieldName => $field) {
            $definition['field_groups'][$field->getGroup()]['fields'][] = $this->definitions[$fieldName];
        }

        return $definition;
    }

    /**
     * Set whether the compiled definition should be cached and reused between requests
     *
     * @param bool $isCachable
     * @param int $ttl Cache time to live in minutes
     *
     * @return $this
     */
    public function setCacheable(bool $isCachable = true, int $ttl = 20160): self
    {
        if ($isCachable && !Cache::supportsTags()) {
            throw new BadMethodCallException('This cache store does not support tagging.');
        }

        $this->cacheable = $isCachable;
        $this->cacheTTL = $ttl;
        return $this;
    }

    public function isCachable(): bool
    {
        return $this->cacheable && Cache::supportsTags();
    }

    public function getCacheTTL(): int
    {
        return $this->cacheTTL;
    }
}
