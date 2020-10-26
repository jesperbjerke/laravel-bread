<?php

namespace Bjerke\Bread\Console\Commands;

use Bjerke\Bread\Tus\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanStaleTusUploadsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bread:clean-tus {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean stale TUS uploads';

    /**
     * Execute the console command
     */
    public function handle()
    {
        if (
            $this->option('force') ||
            // phpcs:ignore Generic.Files.LineLength.TooLong
            $this->confirm('This will remove all directories created in ' . $disk->path('tus') . ' older than '. config('bread.tus_stale_max_age') .'. Proceed?')
        ) {
            $this->info('Removing uploads older than ' . config('bread.tus_stale_max_age'));
            $disk = Storage::disk(config('bread.tus_disk'));
            $directories = $disk->directories('tus');
            $maxAge = strtotime(config('bread.tus_stale_max_age'));

            if (!empty($directories)) {
                foreach ($directories as $directory) {
                    if ($disk->lastModified($directory) <= $maxAge) {
                        $disk->deleteDirectory($directory);
                    }
                }
            }
        }
    }
}
