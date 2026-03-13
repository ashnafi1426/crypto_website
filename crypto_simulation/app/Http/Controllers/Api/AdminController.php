<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function systemMetrics(Request $request): JsonResponse
    {
        // TODO: Implement system metrics
        return response()->json(['message' => 'System metrics endpoint - not implemented yet'], 501);
    }

    public function adjustBalance(Request $request, int $userId): JsonResponse
    {
        // TODO: Implement balance adjustment
        return response()->json(['message' => 'Balance adjustment endpoint - not implemented yet'], 501);
    }

    public function suspiciousActivities(Request $request): JsonResponse
    {
        // TODO: Implement suspicious activities listing
        return response()->json(['message' => 'Suspicious activities endpoint - not implemented yet'], 501);
    }

    public function overridePrice(Request $request, string $symbol): JsonResponse
    {
        // TODO: Implement price override
        return response()->json(['message' => 'Price override endpoint - not implemented yet'], 501);
    }

    public function maintenanceMode(Request $request): JsonResponse
    {
        // TODO: Implement maintenance mode
        return response()->json(['message' => 'Maintenance mode endpoint - not implemented yet'], 501);
    }
}