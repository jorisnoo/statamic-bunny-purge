<?php

namespace Noo\StatamicBunnyPurge\Commands;

use Illuminate\Console\Command;

class StatamicBunnyPurgeCommand extends Command
{
    public $signature = 'statamic-bunny-purge';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
