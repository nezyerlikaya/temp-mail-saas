<?php

namespace App\Http\Controllers;

use App\Services\Mail\InboundMailIntakeService;
use App\Services\Mail\InboundProviderMetricsService;
use App\Services\Mail\MimeValidationService;
use App\Services\Mail\ProviderRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class MailProviderWebhookController extends Controller
{
    public function __invoke(
        string $provider,
        Request $request,
        ProviderRegistryService $registry,
        InboundMailIntakeService $intakes,
        InboundProviderMetricsService $metrics,
        MimeValidationService $mime,
    ): JsonResponse {
        if (! (bool) config('mail-providers.webhooks.enabled', true)) {
            return response()->json(['ok' => false, 'status' => 'disabled'], 404);
        }

        $health = $registry->health($provider);

        if (! $health['configured'] || ! $health['enabled']) {
            $metrics->webhookRejected($provider);

            return response()->json(['ok' => false, 'status' => 'provider_disabled'], 403);
        }

        $payload = $request->json()->all() ?: $request->request->all();
        $headers = collect($request->headers->all())
            ->map(fn (array $values): string => (string) ($values[0] ?? ''))
            ->all();

        $metrics->webhookReceived($provider);

        try {
            $mime->validatePayload($payload);

            $before = \App\Models\InboundMailIntake::query()->count();
            $intake = $intakes->create($payload, $headers, $request->ip(), $provider);
            $duplicate = \App\Models\InboundMailIntake::query()->count() === $before;

            if ($duplicate) {
                $metrics->webhookDuplicate($provider);

                return response()->json(['ok' => true, 'status' => 'duplicate', 'uuid' => $intake->uuid]);
            }

            if (! $intake->signature_valid) {
                $metrics->webhookRejected($provider);

                return response()->json(['ok' => false, 'status' => 'rejected'], 401);
            }

            $metrics->webhookVerified($provider);
            $metrics->webhookProcessed($provider);

            return response()->json(['ok' => true, 'status' => 'queued', 'uuid' => $intake->uuid]);
        } catch (ValidationException $exception) {
            $metrics->webhookRejected($provider);

            return response()->json([
                'ok' => false,
                'status' => 'malformed',
                'message' => collect($exception->errors())->flatten()->first(),
            ], 422);
        }
    }
}
