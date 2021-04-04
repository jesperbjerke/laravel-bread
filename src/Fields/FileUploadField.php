<?php

namespace Bjerke\Bread\Fields;

use Bjerke\Bread\Fields\Base\BaseField;

/**
 * Add a file upload field
 */
class FileUploadField extends BaseField
{
    protected $allowedMimeTypes = [
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

    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        $this->addExtraData([
            'collection' => $name
        ]);
    }

    protected function setDefaultDefinition(): self
    {
        parent::setDefaultDefinition();
        $this->type('MEDIA');
        $this->inputType('file-upload');
        $this->fillable(false);
        $this->addValidation('array');
        $this->defaultValue([]);
        $this->addExtraData([
            'media_type' => 'files',
            'tus' => false,
            'tus_endpoint' => '',
            'mime_types' => $this->allowedMimeTypes
        ]);

        return $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->addExtraData([
            'multiple' => $multiple
        ]);

        return $this;
    }

    public function collection(string $collectionName): self
    {
        $this->addExtraData([
            'collection' => $collectionName
        ]);

        return $this;
    }

    public function allowedMimeTypes(array $mimeTypes): self
    {
        $this->addExtraData([
            'mime_types' => $mimeTypes
        ]);

        return $this;
    }

    public function enableTUS(string $tusEndpoint): self
    {
        $this->addExtraData([
            'tus' => true,
            'tus_endpoint' => $tusEndpoint
        ]);

        return $this;
    }
}
