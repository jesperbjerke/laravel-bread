<?php

namespace Bjerke\Bread\Tus;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TusPhp\Exception\FileException;
use TusPhp\Tus\Server as TusServer;

class Server extends TusServer
{
    public function __construct(Request $request, ?string $chunkId = null, string $cacheAdapter = 'file')
    {
        parent::__construct($cacheAdapter);

        // Set api path dynamically based on current route
        $endpoint = Str::after($request->getUri(), $request->root());
        $endpoint = rtrim(($chunkId) ? str_replace($chunkId, '', $endpoint) : $endpoint, '/');
        $this->setApiPath($endpoint);

        $this->setMaxUploadSize(config('bread.tus_max_upload_size'));

        // If creating a new upload, group the upload into its own folder to avoid
        // conflicts if two users uploads a file with same filename
        if ($request->method() === 'POST') {
            $this->setUploadDir(self::getUploadDirPath($this->getUploadKey(), true));
        }
    }

    public static function getUploadDirPath(string $uploadKey, $makeDir = false): string
    {
        $disk = Storage::disk(config('bread.tus_disk'));
        $uploadDir = 'tus/' . $uploadKey;

        if ($makeDir && !$disk->makeDirectory($uploadDir)) {
            throw new \Exception('Could not create directory: ' . $disk->path($uploadDir));
        }

        return $disk->path($uploadDir);
    }

    public static function getUploadedFilePath(string $uploadKey): string
    {
        $disk = Storage::disk(config('bread.tus_disk'));
        $uploadDir = 'tus/' . $uploadKey;
        $files = $disk->files($uploadDir);

        if (empty($files)) {
            throw new FileException('File does not exist');
        }

        return $disk->path($files[0]);
    }
}
