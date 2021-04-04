<?php

namespace Bjerke\Bread\Traits;

use Bjerke\Bread\Tus\Server as TusServer;
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
     */
    protected array $rules = [];

    /**
     * Determines if validation should be performed automatically before saving
     */
    public bool $validateOnSave = true;

    /**
     * Determines if definition should be compiled as soon as the models construct method is called
     */
    public static bool $defineOnConstruct = false;

    /**
     * Determines if definition should be forced to recompile every time the model is saved
     * Can be useful is saving multiple versions which have dynamic data in the definition
     */
    public static bool $forceDefineOnSave = false;

    public static function boot(): void
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
     */
    public function validate(): \Illuminate\Validation\Validator
    {
        $fillables = $this->getFillable();
        $newAttributes = $this->transformAttributesForValidation($this->getAttributes());
        $attributeKeys = array_keys($newAttributes);

        $rules = array_filter($this->getRules(), static function ($field) use ($fillables, $attributeKeys) {
            $fieldKey = (strpos($field, '.') !== false) ? explode('.', $field)[0] : $field;
            return (in_array($fieldKey, $attributeKeys, true) || in_array($fieldKey, $fillables, true));
        }, ARRAY_FILTER_USE_KEY);

        $validator = $this->applyBeforeValidation(Validator::make($newAttributes, $rules), $rules, $newAttributes);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator;
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
        array $rules,
        array $attributes
    ): \Illuminate\Validation\Validator {
        return $validator;
    }

    /**
     * Reformats attributes before filling model
     *
     * @param array $attributes
     * @param bool $filterFillable Remove all attributes that are not fillable on the model
     *
     * @return array
     */
    public function prepareAttributes(array $attributes = [], bool $filterFillable = true): array
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
    public function transformAttributes(array $rawAttributes): array
    {
        $flatFieldDefinition = $this->getFlatFieldDefinition();

        foreach ($rawAttributes as $fieldName => $fieldValue) {
            if (
                isset($flatFieldDefinition[$fieldName]['type']) &&
                $flatFieldDefinition[$fieldName]['type'] === 'ENUM' &&
                is_array($fieldValue)
            ) {
                $rawAttributes[$fieldName] = implode(';', array_filter($fieldValue, static function ($val) {
                    return $val !== '' && $val !== null;
                }));
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
    public function transformAttributesForValidation(array $rawAttributes): array
    {
        $flatFieldDefinition = $this->getFlatFieldDefinition();

        foreach ($rawAttributes as $fieldName => $fieldValue) {
            if (
                isset($flatFieldDefinition[$fieldName]['type']) &&
                $flatFieldDefinition[$fieldName]['type'] === 'JSON' &&
                Strings::isJson($fieldValue)
            ) {
                // Only encode if not already json
                $rawAttributes[$fieldName] = json_decode($fieldValue, true);
            }
        }

        return $rawAttributes;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function setRules($rules): void
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

    public function syncMediaFiles(
        array $files,
        string $type = 'images',
        string $collection = 'images',
        array $allowedMimeTypes = []
    ): void {
        if (empty($files)) {
            return;
        }

        $filesToRemove = array_filter($files, static function ($file) {
            return (isset($file['remove']) && $file['remove'] === true);
        });
        $filesToAdd = array_filter($files, static function ($file) {
            return (isset($file['add']) && $file['add'] === true);
        });

        // We want to remove images first, to not hit potential collection limit when adding
        foreach ($filesToRemove as $file) {
            try {
                $this->removeMedia($file['id']);
            } catch (\Exception $e) {
                \Log::error($e->getMessage());
            }
        }

        foreach ($filesToAdd as $file) {
            try {
                if ($type === 'images') {
                    if (isset($file['tusKey']) && $file['tusKey']) {
                        $this->addImage(
                            $file['tusKey'],
                            $collection,
                            'TUS',
                            $allowedMimeTypes
                        );
                    } else {
                        $this->addImage(
                            $file['base64'],
                            $collection,
                            'base64',
                            $allowedMimeTypes
                        );
                    }
                } elseif ($type === 'files') {
                    if (isset($file['tusKey']) && $file['tusKey']) {
                        $this->addFile(
                            $file['base64'],
                            $file['name'] ?? null,
                            $collection,
                            'TUS',
                            $allowedMimeTypes
                        );
                    } else {
                        $this->addFile(
                            $file['base64'],
                            $file['name'] ?? null,
                            $collection,
                            'base64',
                            $allowedMimeTypes
                        );
                    }
                }
            } catch (\Exception $e) {
                \Log::error($e->getMessage());
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
    public function validateAndAddMedia(
        string $file,
        array $allowedMimeTypes,
        string $fileFormat = 'base64'
    ): \Spatie\MediaLibrary\MediaCollections\FileAdder {
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
     * @param string $file       Either Base64 string representation of the image to add or TUS upload key
     * @param string $collection Specific collection to add the image to
     * @param string $fileFormat A string representing the format the file is. Either base64 or TUS
     * @param array  $allowedMimeTypes
     *
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     * @throws \Exception
     */
    public function addImage(
        string $file,
        string $collection = 'images',
        string $fileFormat = 'base64',
        array $allowedMimeTypes = []
    ): void {
        if (
            $this instanceof \Spatie\MediaLibrary\HasMedia ||
            $this instanceof \Spatie\MediaLibrary\HasMedia\HasMedia
        ) {
            $this->validateAndAddMedia(
                $file,
                $allowedMimeTypes,
                $fileFormat
            )->toMediaCollection($collection);
        } else {
            throw new \Exception('Class must implement HasMedia');
        }
    }

    /**
     * Add file to collection from base64 string, is based on the field config from FieldDefintion,
     *
     * @param string      $file Either Base64 string representation of the file to add or TUS upload key
     * @param string|null $name Optional "display name", otherwise will use filename that is generated automatically
     * @param string      $collection Specific collection to add the file to
     * @param string      $fileFormat A string representing the format the file is. Either base64 or TUS
     * @param array       $allowedMimeTypes
     *
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     * @throws \Exception
     */
    public function addFile(
        string $file,
        string $name = null,
        string $collection = 'files',
        string $fileFormat = 'base64',
        array $allowedMimeTypes = []
    ): void {
        if (
            $this instanceof \Spatie\MediaLibrary\HasMedia ||
            $this instanceof \Spatie\MediaLibrary\HasMedia\HasMedia
        ) {
            $mediaFile = $this->validateAndAddMedia(
                $file,
                $allowedMimeTypes,
                $fileFormat
            );

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
     * @param int|string $id
     *
     * @throws \Exception|ModelNotFoundException
     */
    public function removeMedia($id): void
    {
        if (
            $this instanceof \Spatie\MediaLibrary\HasMedia ||
            $this instanceof \Spatie\MediaLibrary\HasMedia\HasMedia
        ) {
            $this->media()->findOrFail($id)->delete();
        } else {
            throw new \Exception('Class must implement HasMedia');
        }
    }
}
