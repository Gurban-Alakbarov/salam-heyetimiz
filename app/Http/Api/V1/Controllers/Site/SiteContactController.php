<?php

namespace App\Http\Api\V1\Controllers\Site;

use App\Domain\Site\Jobs\SendSiteLeadEmailJob;
use App\Domain\Site\Models\SiteLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /v1/site/contact — the contact form on the public website (no login; rate limited per IP). `website` is a
 * hidden field people never see: a form that fills it is a bot, and it gets the same answer with nothing stored.
 */
class SiteContactController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'min:7', 'max:40'],
            'place' => ['required', 'string', 'max:80'],
            'topic' => ['sometimes', 'nullable', 'string', 'max:80'],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'locale' => ['sometimes', 'nullable', 'in:az,en,ru'],
            'website' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        if (! empty($data['website'])) {
            return response()->json(['ok' => true], 202);
        }

        $lead = SiteLead::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'place' => $data['place'],
            'topic' => $data['topic'] ?? null,
            'message' => $data['message'] ?? null,
            'locale' => $data['locale'] ?? 'az',
        ]);

        SendSiteLeadEmailJob::dispatch((int) $lead->id);

        return response()->json(['ok' => true], 202);
    }
}
