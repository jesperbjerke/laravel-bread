<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\RelationBaseField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

/**
 * Add a HasMany relation field (search select) to the model
 */
class HasManySelectField extends RelationBaseField
{
    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('HASMANY');
        $this->inputType('model-search');
        $this->addValidation('array');
        $this->fillable(false);
        $this->placeholder(Lang::get('actions.search') . '..');

        $this->addExtraData([
            'multiple' => true,
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
        parent::relation($relation, $model);

        $this->addExtraData([
            'parent_model' => class_basename($model)
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
