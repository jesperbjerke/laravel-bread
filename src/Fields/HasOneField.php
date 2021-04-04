<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\RelationBaseField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Add a HasOne relation field to the model
 */
class HasOneField extends RelationBaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('HASONE');
        $this->inputType('model-search');
        $this->addValidation('numeric');
        $this->fillable(false);
        $this->placeholder(Lang::get('actions.search') . '..');

        $this->addExtraData([
            'multiple' => false,
            'prefetch' => false,
            'search_field' => 'title',
            'display_field' => 'title',
            'extra_display_field' => false,
            'display_field_labels' => false
        ]);

        return $this;
    }

    public function relation(string $relation, Model $model): RelationBaseField
    {
        if (!method_exists($model, 'getRelationType')) {
            throw new InvalidArgumentException(
                'Model ' . class_basename($model) . ' must implement getRelationType (use FieldDefinition trait)'
            );
        }

        $relationInfo = $model->getRelationType($relation);

        if (!isset($this->definition['name']) || !$this->definition['name']) {
            if (!method_exists($model->{$relationInfo['method']}, 'getQualifiedForeignKeyName')) {
                throw new InvalidArgumentException(
                    'Relationship ' . $relation . ' must implement be of type HasOne'
                );
            }
            $this->name($model->{$relationInfo['method']}->getQualifiedForeignKeyName());
        }

        $this->addExtraData([
            'relation' => $relationInfo['method'],
            'related_to' => $relationInfo['model'],
            'endpoint' => Str::lower(Str::plural(Str::kebab($relationInfo['model'])))
        ]);

        return $this;
    }

    public function searchField(string $fieldName = 'title', bool $setDisplayField = true): self
    {
        $data = [
            'search_field' => $fieldName
        ];

        if ($setDisplayField) {
            $date['display_field'] = $fieldName;
        }

        $this->addExtraData($data);
        return $this;
    }

    public function displayField(string $fieldName = 'title'): self
    {
        $this->addExtraData([
            'display_field' => $fieldName
        ]);
        return $this;
    }

    public function prefetch(bool $shouldPrefetch = false): self
    {
        $this->addExtraData([
            'prefetch' => $shouldPrefetch
        ]);
        return $this;
    }

    /**
     * String of model field key to also show when displaying results
     *
     * @param string $fieldName
     *
     * @return $this
     */
    public function extraDisplayField(string $fieldName): self
    {
        $this->addExtraData([
            'extra_display_field' => $fieldName
        ]);
        return $this;
    }

    /**
     * Array of fieldKey => label to provide frontend proper labels
     *
     * @param array $labels
     *
     * @return $this
     */
    public function displayFieldLabels(array $labels): self
    {
        $this->addExtraData([
            'display_field_labels' => $labels
        ]);
        return $this;
    }
}
