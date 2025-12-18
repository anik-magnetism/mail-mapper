<?php

namespace AnikNinja\MailMapper\Traits;

use AnikNinja\MailMapper\Services\EmailMappingService;
use AnikNinja\MailMapper\Jobs\SendEmailNotificationJob;
use AnikNinja\MailMapper\Models\EmailMapping;
use AnikNinja\MailMapper\Services\AttachmentNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Trait NotifiesByEmailMapping
 *
 * Provides a convenient method to trigger dynamic email notifications using the Mail Mapper system.
 * Handles context extraction, meta placeholder management, attachment normalization, and job dispatching.
 *
 * Usage:
 *   $this->notifyByMapping('Sales', 'Leads', 'Create', $model, [
 *       'attachments' => [
 *           '/path/to/file.pdf',
 *           $request->file('upload'),
 *           ['filename' => 'a.txt', 'content' => 'Hello', 'mime' => 'text/plain'],
 *       ],
 *       'custom_var' => 'value'
 *   ]);
 */
trait NotifiesByEmailMapping
{
    /**
     * Notify via email mapping and ensure meta placeholders are stored if not already present.
     * Dispatches a job to send the email asynchronously.
     *
     * Attachments can be provided via $extra['attachments'] as:
     *   - Array of file paths (strings)
     *   - UploadedFile instances (Laravel/Symfony)
     *   - Arrays with 'filename' and 'content' (in-memory)
     *   - URLs (strings, will be passed as 'url' for later download)
     *
     * @param  string  $module   The module name (e.g. 'Sales')
     * @param  string  $menu     The menu or section (e.g. 'Lead Generation')
     * @param  string  $task     The task or action (e.g. 'Create', 'Update')
     * @param  mixed   $modelOrContext  Eloquent model or associative array for context variables
     * @param  array   $extra    Additional context data (merged into context, e.g. attachments, custom vars)
     * @param  bool    $useRaw   If true, forces raw email sending (bypasses Mailable)
     * @return bool    True if the notification was dispatched, false otherwise
     *
     * @example
     *   $this->notifyByMapping('HR', 'Onboarding', 'Welcome', $user, [
     *       'attachments' => [
     *           '/path/to/contract.pdf',
     *           $request->file('resume'),
     *           ['filename' => 'hello.txt', 'content' => 'Hi', 'mime' => 'text/plain'],
     *       ],
     *       'custom_var' => 'value'
     *   ]);
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
                'attachments' => AttachmentNormalizer::normalize($context['attachments'] ?? []),
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
