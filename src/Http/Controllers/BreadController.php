<?php

namespace Bjerke\Bread\Http\Controllers;

use Bjerke\Bread\Tus\CorsMiddleware;
use Bjerke\Bread\Tus\Server;
use Closure;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Str;
use Bjerke\Bread\Models\BreadModel;
use Illuminate\Routing\Controller;
use Bjerke\Bread\Helpers\RequestParams;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Bjerke\ApiQueryBuilder\QueryBuilder;

abstract class BreadController extends Controller
{
    use ValidatesRequests;

    /**
     * Holds fully qualified name for the model class connected to the controller as a string
     * Will be guessed based on controller name if null
     *
     * @var string|null
     */
    protected ?string $modelName = null;

    /**
     * Queries all entries
     *
     * @param Request       $request
     * @param Closure|null $applyQuery Apply custom query before fetching
     *
     * @return LengthAwarePaginator|Builder[]|Collection
     * @throws \Exception
     */
    public function index(Request $request, ?Closure $applyQuery = null)
    {
        $queryBuilder = new QueryBuilder($this->getModel(), $request);

        $query = $queryBuilder->build();

        if ($applyQuery instanceof Closure) {
            $applyQuery($query);
        }

        if (
            ($pagination = $request->input('paginate')) !== null &&
            ($pagination === false || $pagination === 'false' || $pagination === '0')
        ) {
            return $query->get();
        }

        $perPage = $request->input('per_page');

        return $query->paginate($perPage)->appends($request->query());
    }

    /**
     * Perform single resource lookup. Fails if not found.
     * Queries by primary key by default.
     *
     * @param Request      $request
     * @param string|int   $id
     * @param Closure|null $applyQuery Apply custom query before fetching
     *
     * @return Model
     */
    public function findOrFailByQuery(Request $request, $id, ?Closure $applyQuery = null): Model
    {
        if ($applyQuery instanceof Closure) {
            $model = $this->getModel();
            $query = $model->newQuery();
            $query->where($model->getQualifiedKeyName(), $id);
            $applyQuery($query);
            return $query->firstOrFail();
        }

        return $this->getModel()->findOrFail($id);
    }

    /**
     * View single resource
     *
     * @param Request       $request
     * @param int|string    $id
     * @param Closure|null $applyQuery Apply custom query before fetching
     *
     * @return Model
     * @throws NotFoundHttpException
     */
    public function view(Request $request, $id, ?Closure $applyQuery = null): Model
    {
        $model = $this->findOrFailByQuery($request, $id, $applyQuery);

        if (($with = $request->get('with')) !== null) {
            if (is_string($with)) {
                $with = explode(',', $with);
            }
            $model->loadMissing($model->validatedApiRelations(array_map(static function ($relation) {
                return Str::camel($relation);
            }, $with)));
        }

        if (($appends = $request->get('appends')) !== null) {
            if (is_string($appends)) {
                $appends = explode(',', $appends);
            }

            $model->append($model->validatedApiAppends($appends));
        }

        if (($counts = $request->get('counts')) !== null) {
            if (is_string($counts)) {
                $counts = explode(',', $counts);
            }

            $model->loadCount($model->validatedApiCounts(array_map(static function ($relation) {
                return Str::camel($relation);
            }, $counts)));
        }

        return $model;
    }

    /**
     * Saves a new entry in db
     *
     * @param Request $request
     * @param array   $with
     * @param array   $manualAttributes
     * @param Closure|null $beforeSave Apply custom logic before save method is called on model
     *
     * @return bool|Model The newly created model
     * @throws ValidationException
     */
    public function create(
        Request $request,
        array $with = [],
        array $manualAttributes = [],
        ?Closure $beforeSave = null
    ) {
        $model = $this->getModel();
        $attributes = RequestParams::getParams($request);
        $fillables = array_merge(
            $model->prepareAttributes($attributes),
            $model->prepareAttributes($manualAttributes, false)
        );

        $this->beforeModelSave($model, $attributes);

        $model->fill($fillables);

        if ($beforeSave instanceof Closure) {
            $beforeSave($model);
        }

        if ($model->save()) {
            $this->afterModelSave($model, $attributes);
            return $this->loadFresh($request, $model, $with);
        }

        return false;
    }

    /**
     * Update existing entry
     *
     * @param Request       $request
     * @param int|string    $id
     * @param array         $with Eager load relations
     * @param Closure|null $applyQuery Apply custom query before fetching
     * @param Closure|null $beforeSave Apply custom logic before save method is called on model
     *
     * @return bool|null|Model The newly updated model
     * @throws NotFoundHttpException|HttpException|ValidationException
     */
    public function update(
        Request $request,
        $id,
        array $with = [],
        ?Closure $applyQuery = null,
        ?Closure $beforeSave = null
    ) {
        $model = $this->findOrFailByQuery($request, $id, $applyQuery);

        $model->compileDefinition();

        $attributes = RequestParams::getParams($request);
        $fillables = $model->prepareAttributes($attributes);

        $this->beforeModelSave($model, $attributes);

        $model->fill($fillables);

        if ($beforeSave instanceof Closure) {
            $beforeSave($model);
        }

        if ($model->save()) {
            $this->afterModelSave($model, $attributes);
            return $this->loadFresh($request, $model, $with);
        }

        throw new HttpException(500, 'Resource could not be saved');
    }

    /**
     * Delete existing entry
     *
     * @param Request      $request
     * @param int|string   $id
     * @param Closure|null $applyQuery Apply custom query before fetching
     * @param Closure|null $beforeDelete Apply custom logic before delete method is called on model
     *
     * @throws NotFoundHttpException|\Exception
     */
    public function delete(
        Request $request,
        $id,
        ?Closure $applyQuery = null,
        ?Closure $beforeDelete = null
    ): void {
        $model = $this->findOrFailByQuery($request, $id, $applyQuery);

        if ($beforeDelete instanceof Closure) {
            $beforeDelete($model);
        }

        if (!$model->delete()) {
            throw new HttpException(500, 'Resource could not be deleted');
        }
    }

    /**
     * Detach relation from model.
     * Expects $_GET['related_ids'] to be an array of id's to detach, OR $_GET['related_id'] if only detaching one
     *
     * @param Request       $request
     * @param int|string    $id            Model id to perform detach on
     * @param string        $relation      Relation to detach
     * @param Closure|null $applyQuery Apply custom query before fetching
     * @param Closure|null $beforeDetach Apply custom logic before model is detached
     *
     * @throws NotFoundHttpException|\Exception
     */
    public function detach(
        Request $request,
        $id,
        string $relation,
        ?Closure $applyQuery = null,
        ?Closure $beforeDetach = null
    ): void {
        $model = $this->findOrFailByQuery($request, $id, $applyQuery);

        $relatedIds = [];
        $model->compileDefinition();

        $relationType = $model->getRelationType($relation);
        if (!$model->allowRelationChanges($relationType['method'])) {
            throw new HttpException(403, 'Insufficient permissions');
        }

        if ($relatedId = $request->get('related_id')) {
            $relatedIds[] = $relatedId;
        } elseif ($relatedIdsRaw = $request->get('related_ids')) {
            if (is_array($relatedIdsRaw)) {
                $relatedIds = $relatedIdsRaw;
            } else {
                $relatedIds[] = $relatedIdsRaw;
            }
        }

        if (!empty($relatedIds)) {
            if ($beforeDetach instanceof Closure) {
                $beforeDetach($model, $relationType, $relatedIds);
            }

            foreach ($relatedIds as $relatedId) {
                switch ($relationType['type']) {
                    case 'BelongsToMany':
                        $model->{$relationType['method']}()->detach($relatedId);
                        break;
                    case 'HasMany':
                    case 'BelongsTo':
                    default:
                        $model->{$relationType['method']}()->dissociate($relatedId)->save();
                        break;
                }
            }
        }
    }

    /**
     * Attach relation from model.
     * Expects $_GET['related_ids'] to be an array of id's to attach, OR $_GET['related_id'] if only attaching one
     *
     * @param Request       $request
     * @param int|string    $id           Model id to perform attach on
     * @param string        $relation     Relation to attach
     * @param Closure|null  $applyQuery   Apply custom query before fetching
     * @param Closure|null $beforeAttach Apply custom logic before model is attached
     */
    public function attach(
        Request $request,
        $id,
        string $relation,
        ?Closure $applyQuery = null,
        ?Closure $beforeAttach = null
    ): void {
        $model = $this->findOrFailByQuery($request, $id, $applyQuery);

        $relatedIds = [];
        $model->compileDefinition();

        $relationType = $model->getRelationType($relation);
        if (!$model->allowRelationChanges($relationType['method'])) {
            throw new HttpException(403, 'Insufficient permissions');
        }

        if ($relatedId = $request->get('related_id')) {
            $relatedIds[] = $relatedId;
        } elseif ($relatedIdsRaw = $request->get('related_ids')) {
            if (is_array($relatedIdsRaw)) {
                $relatedIds = $relatedIdsRaw;
            } else {
                $relatedIds[] = $relatedIdsRaw;
            }
        }

        if (!empty($relatedIds)) {
            if ($beforeAttach instanceof Closure) {
                $beforeAttach($model, $relationType, $relatedIds);
            }

            foreach ($relatedIds as $relatedId) {
                switch ($relationType['type']) {
                    case 'BelongsToMany':
                        $model->{$relationType['method']}()->attach($relatedId);
                        break;
                    case 'HasMany':
                    case 'BelongsTo':
                    default:
                        $model->{$relationType['method']}()->associate($relatedId)->save();
                        break;
                }
            }
        }
    }

    /**
     * Returns full field definition
     */
    public function definition(Request $request): array
    {
        if ($request->get('flat', false)) {
            return $this->getModel()->getFlatFieldDefinition();
        }

        return $this->getModel()->getFieldDefinition();
    }

    public function tus(Request $request, ?string $chunkId = null)
    {
        if (!class_exists('TusPhp\Tus\Server')) {
            throw new \Exception(
                'The composer package "ankitpokhrel/tus-php" needs to be installed to use TUS uploads'
            );
        }

        $server = new Server($request, $chunkId, config('bread.tus_cache_adapter', 'file'));
        $server->middleware()->add(CorsMiddleware::class);

        return $server->serve()->send();
    }

    /**
     * Returns the model name based on either controller name or modelName property
     */
    protected function getModelClass(): ?string
    {
        // Make sure namespace is appended to provide backwards compatibility
        if ($this->modelName && strpos($this->modelName, '\\') === false) {
            $this->modelName = config('bread.model_namespace') . $this->modelName;
        }

        if (!$this->modelName) {
            $this->modelName = config('bread.model_namespace') . substr(class_basename($this), 0, -10);
        }

        return $this->modelName;
    }

    /**
     * Creates and returns a new model instance from controller specified model
     */
    protected function getModel(array $attributes = [], ?string $connection = null): Model
    {
        $namespacedModelName = $this->getModelClass();

        /**
         * @var $model BreadModel
         */
        $model = new $namespacedModelName($attributes);
        $model->compileDefinition();

        return $model;
    }

    /**
     * Validates an array of key => value pairs against validation rules set up with key => rule
     *
     * @param array $params
     * @param array $rules
     *
     * @throws ValidationException
     */
    protected function validateArray(array $params, array $rules): void
    {
        $validator = $this->getValidationFactory()->make($params, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validates provided meta fields. Takes current meta fields into account
     *
     * @param Model     $model
     * @param string    $relation
     * @param array     $attributes
     * @param array     $fieldsConfig
     *
     * @throws ValidationException
     */
    protected function validateMetaFields(Model $model, string $relation, array $attributes, array $fieldsConfig): void
    {
        $existingMeta = [];

        if ($model->exists && $model->{$relation}->isNotEmpty()) {
            foreach ($model->{$relation} as $meta) {
                $existingMeta[$meta->meta_key] = $meta->meta_value;
            }
        }

        $rawAttrsToValidate = array_merge($existingMeta, $attributes);
        // Format the array to work with dot-notated keys
        $attrsToValidate = [];
        foreach ($rawAttrsToValidate as $key => $value) {
            \Arr::set($attrsToValidate, $key, $value);
        }
        $rules = [];

        foreach ($fieldsConfig as $config) {
            if (!isset($config['name']) && isset($config['fields'])) {
                // Grouped meta field
                foreach ($config['fields'] as $subConfig) {
                    $rules[$subConfig['name']] = $subConfig['rule_string'];
                }
            } else {
                $rules[$config['name']] = $config['rule_string'];
            }
        }

        $this->validateArray($attrsToValidate, $rules);
    }

    /**
     * Saves or updates meta fields based on key => value
     *
     * @param Model   $model
     * @param string  $relation
     * @param array   $attributes
     * @param array   $fieldsConfig
     */
    protected function saveMetaFields(Model $model, string $relation, array $attributes, array $fieldsConfig): void
    {
        if (!$model->allowRelationChanges($relation)) {
            return;
        }

        // Handle nested groups
        $fields = [];
        foreach ($fieldsConfig as $field) {
            if (isset($field['fields'])) {
                foreach ($field['fields'] as $subField) {
                    $fields[] = $subField;
                }
            } else {
                $fields[] = $field;
            }
        }

        foreach ($fields as $field) {
            $key = $field['name'];
            if (array_key_exists($key, $attributes)) {
                $value = ($field['type'] !== 'MEDIA') ? $attributes[$key] : null;

                $metaModel = $model->{$relation}()->updateOrCreate(
                    ['meta_key' => $key],
                    ['meta_value' => $value]
                );

                if ($metaModel && $field['type'] === 'MEDIA') {
                    $metaAttributes = [];
                    $metaAttributes[$key] = $attributes[$key];
                    $this->afterModelSave($metaModel, $metaAttributes, [$field]);
                }
            }
        }
    }

    /**
     * Syncs provided relations on model with sent attributes
     *
     * @param Model $model
     * @param array $attributes
     */
    protected function syncRelations(Model $model, array $attributes): void
    {
        $relationFields = $model->getRemoteRelationFields();

        if (!empty($relationFields)) {
            $attr = collect($attributes);
            foreach ($relationFields as $relationField) {
                if ($attr->has($relationField['name'])) {
                    if (!$model->allowRelationChanges($relationField['extra_data']['relation'])) {
                        continue;
                    }

                    $relationValues = [];

                    $values = $attr->get($relationField['name']);

                    if (is_array($values)) {
                        $relationValues = $values;
                    } elseif (is_numeric($values)) {
                        $relationValues[] = $values;
                    }

                    $model->{$relationField['extra_data']['relation']}()->sync($relationValues);
                }
            }
        }
    }

    /**
     * Validates data that is outside of the model scope (on-save validation) etc
     *
     * @param Model      $model
     * @param array      $attributes
     * @param array|null $definition
     *
     * @return Model
     * @throws ValidationException
     */
    protected function beforeModelSave(Model $model, array $attributes, ?array $definition = null): Model
    {
        $definition = ($definition) ?? $model->getFlatFieldDefinition();
        $metaFields = array_filter($definition, static function ($field) use ($attributes) {
            return $field['type'] === 'META' &&
                   isset($attributes[$field['name']]) &&
                   !empty($attributes[$field['name']]);
        });

        if (!empty($metaFields)) {
            foreach ($metaFields as $field) {
                if (isset($field['extra_data']['fields'], $field['extra_data']['relation'])) {
                    $this->validateMetaFields(
                        $model,
                        $field['extra_data']['relation'],
                        $attributes[$field['name']],
                        $field['extra_data']['fields']
                    );
                }
            }
        }

        return $model;
    }

    /**
     * Saves data that is outside of the model scope (meta fields, relations, media etc)
     *
     * @param Model      $model
     * @param array      $attributes
     * @param array|null $definition
     *
     * @return mixed
     *
     * @params array $attributes
     */
    protected function afterModelSave(Model $model, array $attributes, ?array $definition = null): Model
    {
        $definition = ($definition) ?? $model->getFlatFieldDefinition();
        $metaFields = array_filter($definition, static function ($field) use ($attributes) {
            return $field['type'] === 'META' &&
                   isset($attributes[$field['name']]) &&
                   !empty($attributes[$field['name']]);
        });

        if (!empty($metaFields)) {
            foreach ($metaFields as $field) {
                if (isset($field['extra_data']['fields'], $field['extra_data']['relation'])) {
                    $this->saveMetaFields(
                        $model,
                        $field['extra_data']['relation'],
                        $attributes[$field['name']],
                        $field['extra_data']['fields']
                    );
                }
            }
        }

        $this->syncRelations($model, $attributes);

        $mediaFields = array_filter($definition, static function ($field) use ($attributes) {
            return $field['type'] === 'MEDIA' &&
                   isset($attributes[$field['name']]) &&
                   !empty($attributes[$field['name']]);
        });

        if (!empty($mediaFields)) {
            foreach ($mediaFields as $field) {
                $model->syncMediaFiles(
                    $attributes[$field['name']],
                    $field['extra_data']['media_type'],
                    $field['extra_data']['collection'],
                    $field['extra_data']['mime_types']
                );
            }
        }

        return $model;
    }

    /**
     * Loads a fresh model instance with requested properties/relations
     *
     * @param Request $request
     * @param Model $model
     * @param array $with
     *
     * @return Model
     */
    protected function loadFresh(Request $request, Model $model, array $with = []): Model
    {
        if (($requestedWith = $request->get('with')) !== null) {
            if (is_string($requestedWith)) {
                $requestedWith = explode(',', $requestedWith);
            }

            $with = array_merge($requestedWith, $with);
        }

        $freshModel = $model->fresh($model->validatedApiRelations(array_map(static function ($relation) {
            return Str::camel($relation);
        }, $with)));

        if ($freshModel && (($appends = $request->get('appends')) !== null)) {
            if (is_string($appends)) {
                $appends = explode(',', $appends);
            }

            $freshModel->append($freshModel->validatedApiAppends($appends));
        }

        if ($freshModel && (($counts = $request->get('counts')) !== null)) {
            if (is_string($counts)) {
                $counts = explode(',', $counts);
            }

            $freshModel->loadCount($freshModel->validatedApiCounts(array_map(static function ($relation) {
                return Str::camel($relation);
            }, $counts)));
        }

        return $freshModel;
    }
}
