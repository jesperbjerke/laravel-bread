<?php

namespace Bjerke\Bread\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Bjerke\Bread\Helpers\Strings;
use Bjerke\Bread\Models\BreadModel;

trait BreadModelTrait
{

    /**
     * Validation rules to apply before saving
     *
     * @var array
     */
    protected $rules = [];

    /**
     * Determines if validation should be performed automatically before saving
     * @var bool
     */
    public $validateOnSave = true;

    /**
     * Determines if definition should be compiled as soon as the models construct method is called
     * @var bool
     */
    public static $defineOnConstruct = false;

    /**
     * Determines if definition should be forced to recompile every time the model is saved
     * Can be useful is saving multiple versions which have dynamic data in the definition
     * @var bool
     */
    public static $forceDefineOnSave = false;

    protected $allowedFileMimeTypes = [
        'image/jpeg',
        'image/png',
        'application/msword', // doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', // xls
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
        'application/vnd.ms-powerpoint', // ppt
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // pptx
        'application/vnd.oasis.opendocument.text', // odt
        'application/vnd.oasis.opendocument.presentation', // odp
        'application/vnd.oasis.opendocument.spreadsheet', // ods
        'application/vnd.apple.keynote', // Apple Pages
        'application/x-iwork-pages-sffpages', // Apple Pages
        'application/x-iwork-keynote-sffkey', // Apple Keynote
        'application/vnd.apple.pages', // Apple Keynote
        'application/x-iwork-numbers-sffnumbers', // Apple Numbers
        'application/vnd.apple.numbers', // Apple Numbers
        'text/plain',
        'text/csv',
        'application/pdf',
        'application/zip'
    ];
    protected $allowedImageMimeTypes = [
        'image/jpeg',
        'image/png'
    ];

    public static function boot()
    {
        parent::boot();

        self::saving(static function (Model $model) {
            /**
             * @var $model BreadModel
             */
            if ($model::$forceDefineOnSave || !$model->isDefined()) {
                $model->compileDefinition($model::$forceDefineOnSave);
            }

            if ($model->validateOnSave) {
                $model->validate();
            }
            $model->setRawAttributes($model->transformAttributes($model->getAttributes()));
        });
    }

    /**
     * Runs validation rules found in $this->rules against current model attributes
     *
     * @return \Illuminate\Validation\Validator
     *
     * @throws ValidationException
     */
    public function validate()
    {
        $fillables = $this->getFillable();
        $newAttributes = $this->transformAttributesForValidation($this->getAttributes());
        $attributeKeys = array_keys($newAttributes);

        $rules = array_filter($this->getRules(), static function ($field) use ($fillables, $attributeKeys) {
            $fieldKey = (strpos($field, '.') !== false) ? explode('.', $field)[0] : $field;
            return (in_array($fieldKey, $attributeKeys, true) || in_array($fieldKey, $fillables, true));
        }, ARRAY_FILTER_USE_KEY);

        $Validator = $this->applyBeforeValidation(Validator::make($newAttributes, $rules), $rules, $newAttributes);

        if ($Validator->fails()) {
            throw new ValidationException($Validator);
        }

        return $Validator;
    }

    /**
     * Allows manipulating the Validator instance before the
     * result is evaluated and an exception is thrown.
     * Should always return a Validator instance.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param array                            $rules
     * @param array                            $attributes
     *
     * @return \Illuminate\Validation\Validator
     */
    public function applyBeforeValidation(
        \Illuminate\Validation\Validator $validator,
        $rules,
        $attributes
    ): \Illuminate\Validation\Validator {
        return $validator;
    }

    /**
     * Reformats attributes before filling model
     *
     * @param array $attributes
     * @param boolean $filterFillable Remove all attributes that are not fillable on the model
     *
     * @return array
     */
    public function prepareAttributes($attributes = [], $filterFillable = true)
    {
        if (!$this->isDefined()) {
            $this->compileDefinition();
        }

        // Prepare dates to UTC
        $dateFields = $this->getDates();
        if (!empty($dateFields)) {
            foreach ($dateFields as $dateField) {
                if (isset($attributes[$dateField]) && $attributes[$dateField]) {
                    $attributes[$dateField] = Carbon::parse($attributes[$dateField])
                                                    ->timezone('UTC')
                                                    ->format('Y-m-d H:i:s');
                }
            }
        }

        if ($filterFillable) {
            $fillable = $this->getFillable();
            $attributes = array_filter($attributes, static function ($key) use ($fillable) {
                return in_array($key, $fillable, true);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $attributes;
    }

    /**
     * Prepares fields for saving into DB
     *
     * @param array $rawAttributes
     *
     * @return array
     */
    public function transformAttributes(array $rawAttributes)
    {
        $flatFieldDefinition = $this->getFlatFieldDefinition();

        //$originalAttributes = $this->getAttributes();
        foreach ($rawAttributes as $fieldName => $fieldValue) {
            if (isset($flatFieldDefinition[$fieldName])) {
                switch ($flatFieldDefinition[$fieldName]['type']) {
                    case 'JSON':
                        // Only encode if not already json
                        if (!Strings::isJson($fieldValue)) {
                            $rawAttributes[$fieldName] = json_encode($fieldValue);
                        }
                        break;
                    case 'ENUM':
                        if (is_array($fieldValue)) {
                            $rawAttributes[$fieldName] = implode(';', array_filter($fieldValue, static function ($val) {
                                return $val !== '' && $val !== null;
                            }));
                        }
                        break;
                }
            }
        }

        return $rawAttributes;
    }

    /**
     * Make sure we have the proper validatable formats of the field values before validating
     *
     * @param array $rawAttributes
     *
     * @return array
     */
    public function transformAttributesForValidation(array $rawAttributes)
    {
        $flatFieldDefinition = $this->getFlatFieldDefinition();

        foreach ($rawAttributes as $fieldName => $fieldValue) {
            if (isset($flatFieldDefinition[$fieldName])) {
                switch ($flatFieldDefinition[$fieldName]['type']) {
                    case 'JSON':
                        // Only encode if not already json
                        if (Strings::isJson($fieldValue)) {
                            $rawAttributes[$fieldName] = json_decode($fieldValue, true);
                        }
                        break;
                }
            }
        }

        return $rawAttributes;
    }

    public function getRules()
    {
        return $this->rules;
    }

    public function setRules($rules)
    {
        $this->rules = $rules;
    }

    /**
     * Allows/disallows relation updates in breadcontroller
     *
     * @param null|string $relationName
     *
     * @return bool $allow Default: false
     */
    public function allowRelationChanges($relationName = null): bool
    {
        return false;
    }

    public function syncMediaFiles(array $files, $type = 'images', $collection = 'images')
    {
        if (!empty($files)) {
            foreach ($files as $file) {
                try {
                    if (isset($file['add']) && $file['add'] === true) {
                        switch ($type) {
                            case 'images':
                                $this->addImage($file['base64'], $collection);
                                break;
                            case 'files':
                                $this->addFile(
                                    $file['base64'],
                                    $file['name'] ?? null,
                                    $collection
                                );
                                break;
                        }
                    } elseif (isset($file['remove']) && $file['remove'] === true) {
                        switch ($type) {
                            default:
                                $this->removeMedia($file['id']);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error($e->getMessage());
                }
            }
        }
    }

    /**
     * Add image to collection from base64 string, is based on the field config from FieldDefintion,
     *
     * @param string $base64 Base64 string representation of the image to add
     * @param string $collection Specific collection to add the image to
     *
     * @throws \Exception
     */
    public function addImage($base64, $collection = 'images')
    {
        if ($this instanceof \Spatie\MediaLibrary\HasMedia ||
            $this instanceof \Spatie\MediaLibrary\HasMedia\HasMedia
        ) {
            $this->addMediaFromBase64($base64, $this->allowedImageMimeTypes)->toMediaCollection($collection);
        } else {
            throw new \Exception('Class must implement HasMedia');
        }
    }

    /**
     * Add file to collection from base64 string, is based on the field config from FieldDefintion,
     *
     * @param string $base64 Base64 string representation of the file to add
     * @param string $name Optional "display name", otherwise will use filename that is generated automatically
     * @param string $collection Specific collection to add the file to
     *
     * @throws \Exception
     */
    public function addFile($base64, $name = null, $collection = 'files')
    {
        if ($this instanceof \Spatie\MediaLibrary\HasMedia ||
            $this instanceof \Spatie\MediaLibrary\HasMedia\HasMedia
        ) {
            $file = $this->addMediaFromBase64($base64, $this->allowedFileMimeTypes);

            if ($name) {
                $file->usingName($name);

                $file->addCustomHeaders([
                    'ContentDisposition' => 'attachment; filename="' . $name . '"'
                ]);
            } else {
                $file->addCustomHeaders([
                    'ContentDisposition' => 'attachment'
                ]);
            }

            $file->toMediaCollection($collection);
        } else {
            throw new \Exception('Class must implement HasMedia');
        }
    }

    /**
     * Remove a specific media file related to model
     *
     * @param int $id
     *
     * @throws \Exception|ModelNotFoundException
     */
    public function removeMedia($id)
    {
        if ($this instanceof \Spatie\MediaLibrary\HasMedia ||
            $this instanceof \Spatie\MediaLibrary\HasMedia\HasMedia
        ) {
            $this->media()->findOrFail($id)->delete();
        } else {
            throw new \Exception('Class must HasMedia');
        }
    }

}
