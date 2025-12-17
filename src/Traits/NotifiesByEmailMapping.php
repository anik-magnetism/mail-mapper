<?php

namespace AnikNinja\MailMapper\Traits;

use AnikNinja\MailMapper\Services\EmailMappingService;
use AnikNinja\MailMapper\Jobs\SendEmailNotificationJob;
use AnikNinja\MailMapper\Models\EmailMapping;
use Illuminate\Support\Facades\Log;

trait NotifiesByEmailMapping
{
    /**
     * Notify via email mapping and ensure meta placeholders are stored if not already present.
     * Will dispatch a job to send the email asynchronously.
     * Attachments can be provided via extra['attachments'] as an array.
     * Also supports forcing raw email sending via $useRaw parameter.
     * And logs email sending activity.
     *
     * @param  string  $module   e.g. 'Sales'
     * @param  string  $menu     e.g. 'Lead Generation'
     * @param  string  $task     e.g. 'Create', 'Update'
     * @param  mixed   $modelOrContext  Eloquent model or context array
     * @param  array   $extra    Additional context data to merge like: attachments, urls, etc.
     * @param  bool    $useRaw   Force raw email sending (true/false)
     * @return bool
     */
    public function notifyByMapping(string $module, string $menu, string $task, $modelOrContext = [], array $extra = [], bool $useRaw = false): bool
    {
        try {
            $mappingService = app(EmailMappingService::class);

            // Build context from model or array
            $context = $mappingService->contextFromModel($modelOrContext, $extra);
            $meta = $mappingService->placeholdersFromContext($context);

            // Ensure actor info present
            if (auth()->check()) {
                $context['actor_name'] = $context['actor_name'] ?? auth()->user()->name;
                $context['actor_email'] = $context['actor_email'] ?? auth()->user()->email;
                $meta[] = '{actor_name}';
                $meta[] = '{actor_email}';
                $meta = array_unique($meta);
            }

            // Ensure meta is stored in EmailMapping if not already set
            $this->ensureMetaOnMapping($module, $menu, $task, $meta);

            $emailData = $mappingService->getEmailData($module, $menu, $task, $context);

            if (! $emailData) {
                Log::info("Mail Mapping: No mapping found for {$module} / {$menu} / {$task}");
                return false;
            }

            if ($useRaw) {
                $emailData['use_raw'] = true;
            }

            $payload = [
                'to' => $emailData['to'],
                'cc' => $emailData['cc'],
                'subject' => $emailData['subject'] ?? 'Notification',
                'body' => $emailData['body'] ?? '',
                'meta' => $meta,
                'attachments' => $this->normalizeAttachments($context['attachments'] ?? []),
                'use_raw' => $useRaw,
            ];

            SendEmailNotificationJob::dispatch($payload);

            if (config('mail-mapper.enable_logging')) {
                Log::info("Email mapping triggered for {$module} > {$menu} > {$task}");
            }
            return true;
        } catch (\Throwable $e) {
            Log::error('Mail Mapping failed: ' . $e->getMessage(), [
                'module' => $module,
                'menu' => $menu,
                'task' => $task,
            ]);
            return false;
        }
    }

    /**
     * Normalize attachments into arrays with filename, content and mime.
     * Accepts:
     * - array of ['filename'=>..., 'content'=>..., 'mime'=>...] (kept as-is)
     * - array of local file paths (string) or ['path' => '/abs/path']
     * - UploadedFile instances
     * Returns only valid attachments; invalid entries are skipped.
     *
     * @param array $attachments
     * @return array
     */
    protected function normalizeAttachments(array $attachments): array
    {
        $out = [];

        foreach ($attachments as $att) {
            try {
                // Already normalized
                if (is_array($att) && isset($att['content']) && isset($att['filename'])) {
                    $out[] = [
                        'filename' => $att['filename'],
                        'content' => $att['content'],
                        'mime' => $att['mime'] ?? null,
                    ];
                    continue;
                }

                // UploadedFile / Symfony UploadedFile - preserve path to avoid loading into memory
                if (function_exists('is_a') && (is_object($att) && (is_a($att, \Illuminate\Http\UploadedFile::class) || is_a($att, \Symfony\Component\HttpFoundation\File\UploadedFile::class)))) {
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

                // Array with path key
                if (is_array($att) && isset($att['path']) && is_string($att['path']) && file_exists($att['path'])) {
                    $out[] = [
                        'path' => $att['path'],
                        'filename' => $att['filename'] ?? basename($att['path']),
                        'mime' => $att['mime'] ?? (function_exists('mime_content_type') ? mime_content_type($att['path']) : null),
                    ];
                    continue;
                }

                // String path
                if (is_string($att) && file_exists($att)) {
                    $out[] = [
                        'path' => $att,
                        'filename' => basename($att),
                        'mime' => function_exists('mime_content_type') ? mime_content_type($att) : null,
                    ];
                    continue;
                }

                // Skip anything else
            } catch (\Throwable $e) {
                // skip malformed attachment entries
                continue;
            }
        }

        return $out;
    }

    /**
     * Store meta placeholders in EmailMapping if not already set.
     *
     * @param string $module
     * @param string $menu
     * @param string $task
     * @param array $meta
     * @return void
     */
    protected function ensureMetaOnMapping(string $module, string $menu, string $task, array $meta): void
    {
        $mapping = EmailMapping::where([
            'module' => $module,
            'menu' => $menu,
            'task' => $task,
        ])->first();

        // Update meta if not set or if different from new meta
        if ($mapping && (empty($mapping->meta) || array_values($mapping->meta) !== array_values($meta))) {
            $mapping->meta = $meta;
            $mapping->save();
        }
    }
}
