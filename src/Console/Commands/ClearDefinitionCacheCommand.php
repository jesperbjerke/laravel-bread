<?php

namespace Bjerke\Bread\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearDefinitionCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bread:clear-definitions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear model definition cache';

    /**
     * Execute the console command
     */
    public function handle(): void
    {
        Cache::tags(['bread.model_definitions'])->flush();
    }
}
