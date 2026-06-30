<?php

namespace App\Http\Controllers\Api\V1\Health;

use App\Http\Controllers\Controller;
use App\Services\Monitoring\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HealthCheckController extends Controller
{
    public function __invoke(HealthCheckService $healthCheckService): JsonResponse
    {
        $result = $healthCheckService->run();

        return response()->json([
            'data' => [
                'service' => config('app.name', 'ProcurePilot AI'),
                'environment' => app()->environment(),
                'status' => $result['status'],
                'checked_at' => now()->toISOString(),
                'checks' => $result['checks'],
            ],
        ], $result['status'] === 'ok'
            ? Response::HTTP_OK
            : Response::HTTP_SERVICE_UNAVAILABLE
        );
    }
}
