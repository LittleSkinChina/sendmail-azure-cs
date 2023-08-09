<?php

return function () {
    config(['mail.mailers.azure' => [
        'transport' => 'azure',
        'endpoint' => env('ACS_ENDPOINT'),
        'access_key' => env('ACS_ACCESS_KEY'),
        'api_version' => '2023-03-31',
        'disable_user_tracking' => env('ACS_DISABLE_TRACKING', true),
    ]]);

    if(env('ACS_VERBOSE_LOG', false)) {
        config(['logging.channels.azure-cs' => [
            'driver' => 'single',
            'path' => storage_path('logs/sendmail-azure-cs.log'),
        ]]);
    } else {
        config(['logging.channels.azure-cs' => [
            'driver' => 'null',
        ]]);
    }

    app('mail.manager')->extend('azure', function () {
        return new LittleSkin\SendmailAzureCS\AzureCSMailTransport();
    });
};
