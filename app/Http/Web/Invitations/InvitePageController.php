<?php

namespace App\Http\Web\Invitations;

use App\Domain\Roster\Services\InvitationService;
use App\Http\Api\V1\Controllers\Invitations\InviteController;
use Illuminate\Http\Response;

/**
 * Web landing for an invitation link (/invite/{token}) — shown when the app is not installed (or the link was
 * opened outside the app): who invites, store links and an "open in app" action. It shows exactly what the
 * public lookup API shows; any non-live token renders the same dead state (410).
 */
class InvitePageController
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function show(string $token): Response
    {
        $invitation = $this->invitations->findLive($token);
        $package = (string) config('domain.app_links.android.package');
        $url = rtrim((string) config('domain.invitations.link_base'), '/').'/'.$token;

        return response()->view('invitations.show', [
            'invite' => $invitation !== null ? InviteController::publicView($invitation) : null,
            'openUrl' => $url,
            'androidIntent' => 'intent://'.preg_replace('#^https?://#', '', $url).'#Intent;scheme=https;package='.$package.';end',
            'storeAndroid' => config('domain.app_links.store.android'),
            'storeIos' => config('domain.app_links.store.ios'),
        ], $invitation !== null ? 200 : 410);
    }
}
