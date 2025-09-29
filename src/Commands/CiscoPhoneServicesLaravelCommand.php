<?php

namespace Hwkdo\CiscoPhoneServicesLaravel\Commands;

use Illuminate\Console\Command;

class CiscoPhoneServicesLaravelCommand extends Command
{
    public $signature = 'cisco-phone-services-laravel';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
