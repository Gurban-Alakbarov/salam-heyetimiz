<?php

namespace App\Http\Web\Visitor;

use App\Domain\Visitor\Exceptions\VisitorLinkUnavailableException;
use App\Domain\Visitor\Services\VisitorAccessService;
use App\Domain\Visitor\Support\VisitorPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * The public, no-login visitor page served at /v/{token}. It renders a self-contained mobile page (no
 * external assets) that shows the barrier + who invited them, an "open" button that drives the SAME relay
 * pipeline via the public /v1/visit APIs, and a Directions button to the barrier's coordinates.
 *
 * This controller only renders shell + initial status; every state-changing action goes through the JSON
 * endpoints (VisitorAccessController), so the page holds no privileged logic and exposes no ids.
 */
class VisitorPageController
{
    public function __construct(private readonly VisitorAccessService $access) {}

    public function show(string $token): View|Response
    {
        try {
            $link = $this->access->resolve($token);
        } catch (VisitorLinkUnavailableException) {
            // Unknown / malformed token — render the page in its dead state, never leak whether it existed.
            return response()->view('visitor.show', [
                'token' => $token,
                'status' => VisitorPresenter::notFound(),
                'coordinates' => null,
            ], 404);
        }

        $status = VisitorPresenter::status($link, $this->access->unavailableReason($link));

        return view('visitor.show', [
            'token' => $token,
            'status' => $status,
            // Same coordinates the API exposes (present only while the link is usable). On web we still
            // fall back to a maps URL since a browser cannot fire a native map intent.
            'coordinates' => $status['coordinates'],
        ]);
    }
}
