<?php

namespace Bjerke\Bread\Fields\Base;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;

abstract class RelationBaseField extends BaseField
{
    public function relation(string $relation, Model $model): self
    {
        if (!method_exists($model, 'getRelationType')) {
            throw new InvalidArgumentException(
                'Model ' . class_basename($model) . ' must implement getRelationType (use FieldDefinition trait)'
            );
        }

        $relationInfo = $model->getRelationType($relation);
        $fieldName = ($this->definition['name']) ?: Str::snake($relationInfo['method']);
        $this->name($fieldName);
        $this->addExtraData([
            'relation' => $relationInfo['method'],
            'related_to' => $relationInfo['model'],
            'endpoint' => Str::lower(Str::plural(Str::kebab($relationInfo['model'])))
        ]);

        return $this;
    }

    public function endpoint(string $endpoint): self
    {
        $this->addExtraData([
            'endpoint' => $endpoint
        ]);
        return $this;
    }
}
