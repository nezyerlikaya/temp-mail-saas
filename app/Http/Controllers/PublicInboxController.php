<?php

namespace App\Http\Controllers;

use App\Services\Billing\FeatureGateService;
use App\Services\Mail\PublicInboxMessageService;
use App\Services\Mail\PublicMailboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PublicInboxController extends Controller
{
    public function index(
        Request $request,
        PublicMailboxService $mailboxes,
        FeatureGateService $features,
    ): View {
        return view('pages.inbox', [
            'mailbox' => $mailboxes->current($request),
            'pollingInterval' => (int) $features->featureValue(
                'polling_interval',
                $request->user(),
                config('tempmail.public_inbox.polling_interval_ms', 15000),
            ),
        ]);
    }

    public function generate(Request $request, PublicMailboxService $mailboxes): RedirectResponse
    {
        $mailboxes->generate($request);

        return redirect()->route('inbox.index');
    }

    public function rotate(Request $request, PublicMailboxService $mailboxes): RedirectResponse
    {
        $mailboxes->rotate($request);

        return redirect()->route('inbox.index');
    }

    public function forget(Request $request, PublicMailboxService $mailboxes): RedirectResponse
    {
        $mailboxes->forget($request);

        return redirect()->route('inbox.index');
    }

    public function messages(
        Request $request,
        PublicMailboxService $mailboxes,
        PublicInboxMessageService $messages,
    ): JsonResponse {
        return response()->json([
            'mailbox' => $mailboxes->current($request),
            'messages' => $messages->list($mailboxes->current($request))->all(),
        ]);
    }

    public function show(
        Request $request,
        string $uuid,
        PublicMailboxService $mailboxes,
        PublicInboxMessageService $messages,
    ): JsonResponse {
        $message = $messages->show($mailboxes->current($request), $uuid);

        abort_if($message === null, 404);

        return response()->json([
            'message' => $message,
        ]);
    }
}
