<?php

namespace AnikNinja\MailMapper\Repositories;

use AnikNinja\MailMapper\Models\EmailMapping;
use AnikNinja\MailMapper\Contracts\EmailMappingRepositoryContract;

class EmailMappingRepository implements EmailMappingRepositoryContract
{
    public function all()
    {
        return EmailMapping::latest()->get();
    }

    public function find(int $id): EmailMapping
    {
        return EmailMapping::findOrFail($id);
    }

    public function create(array $data): EmailMapping
    {
        return EmailMapping::create($data);
    }

    public function update(int $id, array $data): EmailMapping
    {
        $mapping = $this->find($id);
        $mapping->update($data);

        return $mapping;
    }

    public function delete(int $id): bool
    {
        return $this->find($id)->delete();
    }
}
