<?php

use AnikNinja\MailMapper\Services\EmailMappingService;
use AnikNinja\MailMapper\Jobs\SendEmailNotificationJob;

if (! function_exists('mail_mapper_normalize_attachments')) {
    function mail_mapper_normalize_attachments(array $attachments): array
    {
        $out = [];
        foreach ($attachments as $att) {
            try {
                // already normalized (in-memory)
                if (is_array($att) && isset($att['content']) && isset($att['filename'])) {
                    $out[] = [
                        'filename' => $att['filename'],
                        'content' => $att['content'],
                        'mime' => $att['mime'] ?? null,
                    ];
                    continue;
                }

                // UploadedFile instances - preserve path
                if (is_object($att) && ($att instanceof \Illuminate\Http\UploadedFile || $att instanceof \Symfony\Component\HttpFoundation\File\UploadedFile)) {
                    $path = $att->getRealPath();
                    if ($path && file_exists($path)) {
                        $out[] = [
                            'path' => $path,
                            'filename' => $att->getClientOriginalName() ?: basename($path),
                            'mime' => $att->getClientMimeType() ?? null,
                        ];
                    }
                    continue;
                }

                // array with path
                if (is_array($att) && isset($att['path']) && is_string($att['path']) && file_exists($att['path'])) {
                    $out[] = [
                        'path' => $att['path'],
                        'filename' => $att['filename'] ?? basename($att['path']),
                        'mime' => $att['mime'] ?? (function_exists('mime_content_type') ? mime_content_type($att['path']) : null),
                    ];
                    continue;
                }

                // string file path
                if (is_string($att) && file_exists($att)) {
                    $out[] = [
                        'path' => $att,
                        'filename' => basename($att),
                        'mime' => function_exists('mime_content_type') ? mime_content_type($att) : null,
                    ];
                    continue;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return $out;
    }
}

if (! function_exists('notify_email_mapping')) {
    function notify_email_mapping(string $module, string $menu, string $task, array $context = []): bool
    {
        $svc = app(EmailMappingService::class);
        $emailData = $svc->getEmailData($module, $menu, $task, $context);
        if (! $emailData) return false;
        // preserve provided context as meta (backwards compatibility)
        $emailData['meta'] = $context;

        // normalize and attach attachments if present in context
        if (! empty($context['attachments']) && is_array($context['attachments'])) {
            $emailData['attachments'] = mail_mapper_normalize_attachments($context['attachments']);
        }

        SendEmailNotificationJob::dispatch($emailData);
        return true;
    }
}
