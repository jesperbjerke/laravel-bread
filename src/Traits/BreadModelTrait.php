<?php

namespace Bjerke\Bread\Traits;

use Bjerke\Bread\Tus\Server as TusServer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                                if (isset($file['tusKey']) && $file['tusKey']) {
                                    $this->addImage($file['tusKey'], $collection, 'TUS');
                                } else {
                                    $this->addImage($file['base64'], $collection, 'base64');
                                }
                                break;
                            case 'files':
                                if (isset($file['tusKey']) && $file['tusKey']) {
                                    $this->addFile(
                                        $file['base64'],
                                        $file['name'] ?? null,
                                        $collection,
                                        'TUS'
                                    );
                                } else {
                                    $this->addFile(
                                        $file['base64'],
                                        $file['name'] ?? null,
                                        $collection,
                                        'base64'
                                    );
                                }
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
     * Returns a media library file adder instance based on either base64 or a TUS upload key
     * Validates the file against allowed mime types
     *
     * @param string $file Either Base64 string representation of the file to add or TUS upload key
     * @param array  $allowedMimeTypes Array of allowed mime types
     * @param string $fileFormat A string representing the format the file is. Either base64 or TUS
     *
     * @return \Spatie\MediaLibrary\MediaCollections\FileAdder
     */
    public function validateAndAddMedia(string $file, array $allowedMimeTypes, $fileFormat = 'base64')
    {
        if ($fileFormat === 'base64') {
            $mediaFile = $this->addMediaFromBase64($file, $allowedMimeTypes);
        } else {
            $filePath = TusServer::getUploadedFilePath($file);
            $this->guardAgainstInvalidMimeType($filePath, $allowedMimeTypes);
            $mediaFile = $this->addMedia($filePath);
        }

        return $mediaFile;
    }

    /**
     * Add image to collection from base64 string, is based on the field config from FieldDefintion,
     *
     * @param string $file Either Base64 string representation of the image to add or TUS upload key
     * @param string $collection Specific collection to add the image to
     * @param string $fileFormat A string representing the format the file is. Either base64 or TUS
     *
     * @throws \Exception
     */
    public function addImage($file, $collection = 'images', $fileFormat = 'base64')
    {
        if ($this instanceof \Spatie\MediaLibrary\HasMedia ||
            $this instanceof \Spatie\MediaLibrary\HasMedia\HasMedia
        ) {
            $this->validateAndAddMedia($file, $this->allowedImageMimeTypes, $fileFormat)->toMediaCollection($collection);
        } else {
            throw new \Exception('Class must implement HasMedia');
        }
    }

    /**
     * Add file to collection from base64 string, is based on the field config from FieldDefintion,
     *
     * @param string $file Either Base64 string representation of the file to add or TUS upload key
     * @param string $name Optional "display name", otherwise will use filename that is generated automatically
     * @param string $collection Specific collection to add the file to
     * @param string $fileFormat A string representing the format the file is. Either base64 or TUS
     *
     * @throws \Exception
     */
    public function addFile($file, $name = null, $collection = 'files', $fileFormat = 'base64')
    {
        if ($this instanceof \Spatie\MediaLibrary\HasMedia ||
            $this instanceof \Spatie\MediaLibrary\HasMedia\HasMedia
        ) {
            $mediaFile = $this->validateAndAddMedia($file, $this->allowedFileMimeTypes, $fileFormat);

            if ($name) {
                $mediaFile->usingName($name);

                $mediaFile->addCustomHeaders([
                    'ContentDisposition' => 'attachment; filename="' . $name . '"'
                ]);
            } else {
                $mediaFile->addCustomHeaders([
                    'ContentDisposition' => 'attachment'
                ]);
            }

            $mediaFile->toMediaCollection($collection);
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
            throw new \Exception('Class must implement HasMedia');
        }
    }

}
