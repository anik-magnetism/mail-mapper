<?php

namespace AnikNinja\MailMapper\Services;

use AnikNinja\MailMapper\Models\EmailMapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmailMappingService
{
    protected const CACHE_TTL = 259200; // 72 hours (in seconds)
    protected const WILDCARD = '*';

    /**
     * Get email data based on module, menu, task, and context.
     * Returns null if no active mapping or no valid recipients.
     * @param string $module
     * @param string $menu
     * @param string $task
     * @param array $context
     * @return array|null
     */
    public function getEmailData(string $module, string $menu, string $task, array $context = []): ?array
    {
        // Get mapping template (cached) — cache key does NOT include $context
        $mapping = $this->cachedResolveMapping($module, $menu, $task);
        if (! $mapping || ! $mapping->is_active) {
            Log::info("No active email mapping found for {$module}, {$menu}, {$task}");
            return null;
        }

        // Apply placeholders to templates at runtime (context-specific)
        $to = $this->applyPlaceholdersToArray($mapping->to ?? [], $context);
        $cc = $this->applyPlaceholdersToArray($mapping->cc ?? [], $context);

        $subject = $this->applyPlaceholders($mapping->subject ?? '', $context);
        $body = $this->applyPlaceholders($mapping->body ?? '', $context);

        if (! empty($context['actor_email']) && in_array('{actor_email}', $mapping->meta ?? [])) {
            $cc[] = $context['actor_email'];
        }

        // Normalize and validate email addresses
        $to = $this->normalizeEmails($to);
        $cc = $this->normalizeEmails($cc);

        if (empty($to) && empty($cc)) {
            Log::warning("No valid recipients found for email mapping ID {$mapping->id}");
            return null;
        }

        return [
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'body' => $body,
            'mapping_id' => $mapping->id,
        ];
    }

    /**
     * Cached resolver for mapping template (no context applied).
     */
    protected function cachedResolveMapping(string $module, string $menu, string $task)
    {
        $cacheKey = $this->cacheKey($module, $menu, $task) . ':template';
        try {
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($module, $menu, $task) {
                return $this->resolveMapping($module, $menu, $task);
            });
        } catch (\Throwable $e) {
            Log::warning("Cache error while reading mapping template: " . $e->getMessage());
            // Fallback to DB lookup if cache fails
            return $this->resolveMapping($module, $menu, $task);
        }
    }

    /**
     * Find the most specific active mapping based on module, menu, and task.
     * Fallback to wildcards as needed.
     * @param string $module
     * @param string $menu
     * @param string $task
     * @return EmailMapping|null
     */
    protected function resolveMapping(string $module, string $menu, string $task)
    {
        $candidates = [
            [$module, $menu, $task],
            [$module, $menu, self::WILDCARD],
            [$module, self::WILDCARD, $task],
            [$module, self::WILDCARD, self::WILDCARD],
            [self::WILDCARD, $menu, $task],
            [self::WILDCARD, $menu, self::WILDCARD],
            [self::WILDCARD, self::WILDCARD, $task],
            [self::WILDCARD, self::WILDCARD, self::WILDCARD],
        ];

        foreach ($candidates as $parts) {
            $mapping = EmailMapping::query()
                ->where('module', $parts[0])
                ->where('menu', $parts[1])
                ->where('task', $parts[2])
                ->where('is_active', true)
                ->orderBy('id', 'desc')
                ->first();

            if ($mapping) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Replace placeholders in the format {key} with values from context.
     * @param string $text
     * @param array $context
     * @return string
     */
    protected function applyPlaceholders(string $text, array $context = []): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($context) {
            return $context[$matches[1]] ?? $matches[0];
        }, $text);
    }

    /**
     * Apply placeholders to each string in the array.
     * @param array $arr
     * @param array $context
     * @return array
     */
    protected function applyPlaceholdersToArray(array $arr, array $context = []): array
    {
        return array_map(function ($item) use ($context) {
            return is_string($item) ? $this->applyPlaceholders($item, $context) : $item;
        }, $arr);
    }

    /**
     * Normalize and validate email addresses from an array of strings.
     * Removes duplicates and empty entries.
     * Validates email format.
     * Logs warnings for invalid emails.
     * @param array $emails
     * @return array
     */
    protected function normalizeEmails(array $emails): array
    {
        $flat = [];
        foreach ($emails as $e) {
            $e = trim($e);
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $flat[] = $e;
            } else {
                Log::warning("Invalid email address: {$e}");
            }
        }
        return array_values(array_unique($flat));
    }

    /**
     * Build context array from an Eloquent model using its fillable attributes.
     * Includes id, created_at and updated_at automatically and merges any $extra keys.
     *
     * @param  object|mixed  $model
     * @param  array  $extra
     * @return array
     */
    public function contextFromModel($model, array $extra = []): array
    {
        if (!is_object($model)) {
            return $extra;
        }

        $data = [];

        if (method_exists($model, 'getFillable') && count($model->getFillable()) > 0) {
            foreach ($model->getFillable() as $key) {
                $data[$key] = $model->{$key} ?? null;
            }
        } else {
            foreach ($model->getAttributes() as $k => $v) {
                $data[$k] = $v;
            }
        }

        // include common fields even if not fillable
        foreach (['id', 'created_at', 'updated_at'] as $k) {
            if (isset($model->{$k}) && ! array_key_exists($k, $data)) {
                $data[$k] = $model->{$k};
            }
        }

        // optionally include relations if provided in $extra['with'] (not required)
        // merge and return
        return array_merge($data, $extra);
    }

    /**
     * Generate placeholder keys from an Eloquent model's attributes.
     * @param mixed $context
     * @return array
     */
    public function placeholdersFromcontext($context): array
    {
        return array_map(fn($key) => '{' . $key . '}', array_keys($context));
    }

    /**
     * Generate a cache key for the given module, menu, and task.
     * @param string $module
     * @param string $menu
     * @param string $task
     * @return string
     */
    protected function cacheKey(string $module, string $menu, string $task): string
    {
        return "email_mapping:{$module}:{$menu}:{$task}";
    }

    /**
     * Clear cache entries related to a specific email mapping.
     * Should be called after creating, updating, or deleting a mapping.
     * @param EmailMapping $mapping
     * @return void
     */
    public function clearCacheForMapping(EmailMapping $mapping): void
    {
        $patterns = [
            $this->cacheKey($mapping->module, $mapping->menu, $mapping->task) . ':template',
            $this->cacheKey($mapping->module, $mapping->menu, self::WILDCARD) . ':template',
            $this->cacheKey($mapping->module, self::WILDCARD, $mapping->task) . ':template',
            $this->cacheKey($mapping->module, self::WILDCARD, self::WILDCARD) . ':template',
            $this->cacheKey(self::WILDCARD, $mapping->menu, $mapping->task) . ':template',
            $this->cacheKey(self::WILDCARD, $mapping->menu, self::WILDCARD) . ':template',
            $this->cacheKey(self::WILDCARD, self::WILDCARD, $mapping->task) . ':template',
            $this->cacheKey(self::WILDCARD, self::WILDCARD, self::WILDCARD) . ':template',
        ];

        foreach ($patterns as $k) {
            Cache::forget($k);
        }
    }
}
