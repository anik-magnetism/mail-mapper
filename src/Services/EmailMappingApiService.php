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
        // Support pagination via ?per_page= param. If per_page is omitted or set to 0, return all records.
        $perPage = (int) request()->query('per_page', config('mail-mapper.api.per_page', 15));
        $maxPerPage = (int) config('mail-mapper.api.max_per_page', 100);

        if ($perPage <= 0) {
            return $this->repository->all();
        }

        $perPage = min(max(1, $perPage), $maxPerPage);

        return EmailMapping::latest()->paginate($perPage);
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
