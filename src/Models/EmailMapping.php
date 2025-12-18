<?php

namespace AnikNinja\MailMapper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailMapping extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'module',
        'menu',
        'task',
        'to',
        'cc',
        'subject',
        'body',
        'is_active',
        'meta',
        'last_updated_by'
    ];

    protected $casts = [
        'to' => 'array',
        'cc' => 'array',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(config('mail-mapper.user_model'), 'last_updated_by');
    }

    /**
     * Accept string like "email1,email2" or "email1; email2" or array.
     * Always store as array of trimmed email strings.
     */
    public function setToAttribute($value)
    {
        $parts = preg_split('/[,;\s]+/', $value);
        $this->attributes['to'] = json_encode(array_map('trim', $parts));
    }

    /**
     * Get the "to" attribute as an array.
     */
    public function getToAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return [];
        }
        return json_decode($value, true) ?: [];
    }

    /**
     * Accept string like "email1,email2" or "email1; email2" or array.
     * Always store as array of trimmed email strings.
     */
    public function setCcAttribute($value)
    {
        $parts = preg_split('/[,;\s]+/', $value);
        $this->attributes['cc'] = json_encode(array_map('trim', $parts));
    }

    /**
     * Get the "cc" attribute as an array.
     */
    public function getCcAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return [];
        }
        return json_decode($value, true) ?: [];
    }

    /**
     * Accept string like "client_name,actor_name" or "{client_name},{actor_name}" or array.
     * Always store as array of placeholders with braces.
     */
    public function setMetaAttribute($value)
    {
        $items = [];

        if (is_string($value)) {
            $items = preg_split('/[,;]+/', $value);
        } elseif (is_array($value)) {
            $items = $value;
        }

        $normalized = array_values(array_filter(array_map(function ($item) {
            $item = trim($item);
            if ($item === '') return null;
            // strip existing braces then re-wrap to ensure "{key}"
            $item = trim($item, '{} ');
            return '{' . $item . '}';
        }, $items)));

        $this->attributes['meta'] = json_encode($normalized);
    }

    public function getMetaAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return [];
        }
        return json_decode($value, true) ?: [];
    }
}
