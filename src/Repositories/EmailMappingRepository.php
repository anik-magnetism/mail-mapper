<?php

namespace AnikNinja\MailMapper\Repositories;

use AnikNinja\MailMapper\Models\EmailMapping;
use AnikNinja\MailMapper\Contracts\EmailMappingRepositoryContract;

class EmailMappingRepository implements EmailMappingRepositoryContract
{
    private array $error_message;

    public function __construct()
    {
        $this->error_message = [
            'not_exist' => 'Email mapping does not exist.',
            'exist' => 'Email mapping already exists.',
        ];
    }
    public function all()
    {
        return EmailMapping::latest()->get();
    }

    public function find(int $id): EmailMapping
    {
        try {
            return EmailMapping::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \InvalidArgumentException($this->error_message['not_exist']);
        }
    }

    public function create(array $data): EmailMapping
    {
        try {
            $existing = EmailMapping::where('module', $data['module'])
                ->where('menu', $data['menu'])
                ->where('task', $data['task'])
                ->first();

            if ($existing) {
                throw new \InvalidArgumentException($this->error_message['exist']);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Do nothing, proceed to create
        }
        return EmailMapping::create($data);
    }

    public function update(int $id, array $data): EmailMapping
    {
        try {
            $mapping = $this->find($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \InvalidArgumentException($this->error_message['not_exist']);
        }

        $mapping->update($data);

        return $mapping;
    }

    public function delete(int $id): bool
    {
        try {
            $this->find($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \InvalidArgumentException($this->error_message['not_exist']);
        }
        return $this->find($id)->delete();
    }
}
