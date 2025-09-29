<?php

namespace Hwkdo\CiscoPhoneServicesLaravel;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Hwkdo\CiscoPhoneServicesLaravel\Commands\CiscoPhoneServicesLaravelCommand;

class CiscoPhoneServicesLaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('cisco-phone-services-laravel')
            ->hasConfigFile();
            #->hasViews()
            #->hasMigration('create_cisco_phone_services_laravel_table')
            -#>hasCommand(CiscoPhoneServicesLaravelCommand::class);
    }
}
