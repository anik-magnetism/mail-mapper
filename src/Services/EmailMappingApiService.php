<?php

namespace AnikNinja\MailMapper\Services;

use AnikNinja\MailMapper\Repositories\EmailMappingRepository;
use AnikNinja\MailMapper\Models\EmailMapping;

class EmailMappingApiService
{
    public function __construct(
        protected EmailMappingRepository $repository
    ) {}

    public function list()
    {
        return $this->repository->all();
    }

    public function show(int $id): EmailMapping
    {
        return $this->repository->find($id);
    }

    public function create(array $data): EmailMapping
    {
        return $this->repository->create($this->normalize($data));
    }

    public function update(int $id, array $data): EmailMapping
    {
        return $this->repository->update($id, $this->normalize($data));
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    protected function normalize(array $data): array
    {
        return [
            'module' => $data['module'],
            'menu' => $data['menu'],
            'task' => $data['task'],
            'to' => array_values($data['to']),
            'cc' => array_values($data['cc']),
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];
    }
}
