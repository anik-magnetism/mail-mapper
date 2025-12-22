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
        try {
            return new EmailMappingResource(
                $this->service->show($id)
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                422
            );
        }
    }

    public function store(StoreEmailMappingRequest $request)
    {
        try {
            return response()->json(
                [
                    'message' => 'Email Mapping created successfully.',
                    'data' => new EmailMappingResource(
                        $this->service->create($request->validated())
                    ),
                ],
                201
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                422
            );
        }
    }

    public function update(UpdateEmailMappingRequest $request, int $id)
    {
        try {
            return response()->json(
                [
                    'message' => 'Email Mapping updated successfully.',
                    'data' => new EmailMappingResource(
                        $this->service->update($id, $request->validated())
                    ),
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                422
            );
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->service->delete($id);
            return response()->json(
                [
                    'message' => 'Email Mapping deleted successfully.',
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(
                [
                    'message' => $e->getMessage(),
                ],
                422
            );
        }
    }
}
