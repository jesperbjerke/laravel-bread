<?php

namespace Bjerke\Bread\Models;

use Illuminate\Database\Eloquent\Model;
use Bjerke\ApiQueryBuilder\QueryBuilderModelTrait;
use Bjerke\Bread\Traits\BreadModelTrait;
use Bjerke\Bread\Traits\FieldDefinition;

/**
 * Class BreadModel
 * @package Bjerke\Bread\Models
 *
 * @mixin \Eloquent
 */
abstract class BreadModel extends Model
{
    use FieldDefinition;
    use QueryBuilderModelTrait;
    use BreadModelTrait;

    public function __construct(array $attributes = [])
    {
        if (self::$defineOnConstruct && !$this->isDefined()) {
            $this->compileDefinition();
        }

        parent::__construct($attributes);
    }

}
