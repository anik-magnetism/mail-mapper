<?php

use AnikNinja\MailMapper\Services\EmailMappingService;
use AnikNinja\MailMapper\Jobs\SendEmailNotificationJob;

if (! function_exists('notify_email_mapping')) {
    function notify_email_mapping(string $module, string $menu, string $task, array $context = []): bool
    {
        $svc = app(EmailMappingService::class);
        $emailData = $svc->getEmailData($module, $menu, $task, $context);
        if (! $emailData) return false;
        $emailData['meta'] = $context;
        SendEmailNotificationJob::dispatch($emailData);
        return true;
    }
}
