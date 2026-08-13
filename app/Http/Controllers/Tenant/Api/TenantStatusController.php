<?php

namespace App\Http\Controllers\Tenant\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TenantStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'tenant_id' => tenant('id'),
            'status' => tenant('status')?->value ?? tenant('status'),
        ]);
    }
}
