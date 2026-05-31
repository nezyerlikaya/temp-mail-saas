<?php

namespace App\Http\Controllers;

use App\Services\Billing\BillingWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BillingWebhookController extends Controller
{
    public function __invoke(string $provider, Request $request, BillingWebhookService $webhooks): JsonResponse
    {
        $result = $webhooks->handle($provider, $request);

        return response()->json([
            'ok' => $result['ok'],
            'status' => $result['status'],
        ], $result['status'] === 'rejected' ? 401 : 200);
    }
}
