<?php

namespace App\Http\Web\Invitations;

use Illuminate\Http\JsonResponse;

/** Android App Links + iOS universal-link association files for the invitation links (B6). */
class WellKnownController
{
    /** GET /.well-known/assetlinks.json */
    public function assetLinks(): JsonResponse
    {
        $fingerprints = (array) config('domain.app_links.android.sha256_cert_fingerprints', []);
        abort_if($fingerprints === [], 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => (string) config('domain.app_links.android.package'),
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]], 200, [], JSON_UNESCAPED_SLASHES);
    }

    /** GET /.well-known/apple-app-site-association (no extension; served as JSON) */
    public function appleAppSiteAssociation(): JsonResponse
    {
        $appId = (string) config('domain.app_links.ios.app_id', '');
        abort_if($appId === '', 404);

        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [[
                    'appIDs' => [$appId],
                    'components' => array_map(fn (string $p): array => ['/' => $p], (array) config('domain.app_links.paths', [])),
                ]],
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
