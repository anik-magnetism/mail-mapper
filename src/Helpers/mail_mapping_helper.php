<?php

use AnikNinja\MailMapper\Services\EmailMappingService;
use AnikNinja\MailMapper\Jobs\SendEmailNotificationJob;
use AnikNinja\MailMapper\Services\AttachmentNormalizer;

/**
 * Helper function for dispatching a dynamic email notification using Mail Mapper.
 *
 * Usage:
 *   notify_email_mapping('Sales', 'Leads', 'Create', [
 *       'client_name' => 'Acme',
 *       'attachments' => [
 *           '/path/to/file.pdf',
 *           $request->file('upload'),
 *           ['filename' => 'hello.txt', 'content' => 'Hi', 'mime' => 'text/plain'],
 *       ],
 *       'custom_var' => 'value'
 *   ]);
 *
 * @param string $module   The module name (e.g. 'Sales')
 * @param string $menu     The menu or section (e.g. 'Lead Generation')
 * @param string $task     The task or action (e.g. 'Create', 'Update')
 * @param array  $context  Context variables for template placeholders and attachments
 *                         - 'attachments' key (optional): array of file paths, UploadedFile instances,
 *                           or arrays with 'filename' and 'content'
 * @return bool  True if the notification was dispatched, false otherwise
 */
if (! function_exists('notify_email_mapping')) {
    function notify_email_mapping(string $module, string $menu, string $task, array $context = []): bool
    {
        $svc = app(EmailMappingService::class);
        $emailData = $svc->getEmailData($module, $menu, $task, $context);
        if (! $emailData) return false;
        $emailData['meta'] = $context;

        if (! empty($context['attachments']) && is_array($context['attachments'])) {
            $emailData['attachments'] = AttachmentNormalizer::normalize($context['attachments']);
        }

        SendEmailNotificationJob::dispatch($emailData);
        return true;
    }
}
