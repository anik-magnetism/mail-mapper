<?php

namespace AnikNinja\MailMapper\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use AnikNinja\MailMapper\Services\EmailMappingApiService;
use AnikNinja\MailMapper\Http\Requests\StoreEmailMappingRequest;
use AnikNinja\MailMapper\Http\Requests\UpdateEmailMappingRequest;
use AnikNinja\MailMapper\Http\Resources\EmailMappingResource;


class EmailMappingController extends Controller
{
    public function __construct(
        protected EmailMappingApiService $service,
    ) {}

    public function index()
    {
        return EmailMappingResource::collection(
            $this->service->list()
        );
    }

    public function show(int $id)
    {
        return new EmailMappingResource(
            $this->service->show($id)
        );
    }

    public function store(StoreEmailMappingRequest $request)
    {
        return response()->json(
            [
                'message' => 'Email Mapping created successfully.',
                'data' => new EmailMappingResource(
                    $this->service->create($request->validated())
                ),
            ],
            201
        );
    }

    public function update(UpdateEmailMappingRequest $request, int $id)
    {
        return response()->json(
            [
                'message' => 'Email Mapping updated successfully.',
                'data' => new EmailMappingResource(
                    $this->service->update($id, $request->validated())
                ),
            ]
        );
    }

    public function destroy(int $id)
    {
        $this->service->delete($id);

        return response()->json(
            [
                'message' => 'Email Mapping deleted successfully.',
            ]
        );
    }
}
