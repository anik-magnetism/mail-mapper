<?php

namespace AnikNinja\MailMapper\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmailMappingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module,
            'menu' => $this->menu,
            'task' => $this->task,
            'to' => $this->to,
            'cc' => $this->cc,
            'subject' => $this->subject,
            'body' => $this->body,
            'is_active' => $this->is_active,
            'meta' => $this->meta,
            'last_updated_by' => $this->last_updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
