<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Services\Access\AccessScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessScanController extends Controller
{
    public function __construct(
        private readonly AccessScanService $accessScanService
    ) {
    }

    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'device_uuid' => ['required', 'string', 'max:120'],
            'event_type' => ['nullable', 'in:entry,exit,access'],
            'scanned_at' => ['nullable', 'date'],
        ]);

        $result = $this->accessScanService->process($data, $request->user());

        $httpCode = $result['http_code'] ?? 200;
        unset($result['http_code']);

        return response()->json($result, $httpCode);
    }
}