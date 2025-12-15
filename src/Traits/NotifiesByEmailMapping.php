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
     *
     * @param  string  $module   e.g. 'Sales'
     * @param  string  $menu     e.g. 'Lead Generation'
     * @param  string  $task     e.g. 'Create', 'Update'
     * @param  mixed   $modelOrContext  Eloquent model or context array
     * @param  array   $extra    Additional context data to merge
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
                'use_raw' => $useRaw,
            ];

            SendEmailNotificationJob::dispatch($payload);

            Log::info("Email mapping triggered for {$module} > {$menu} > {$task}");
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
