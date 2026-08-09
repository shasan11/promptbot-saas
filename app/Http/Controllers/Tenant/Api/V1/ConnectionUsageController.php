<?php

namespace App\Http\Controllers\Tenant\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Connections\Connection;
use App\Services\Connections\ConnectionUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectionUsageController extends Controller
{
    public function show(Request $request, Connection $connection, ConnectionUsageService $usage): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'data' => $usage->summary(
                $connection->loadMissing('integration:id,key,name,provider'),
                $data['from'] ?? null,
                $data['to'] ?? null,
            ),
        ]);
    }
}
