<?php

use App\Http\Web\Visitor\VisitorPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes — admin Blade panel (Backend Arch §11)
|--------------------------------------------------------------------------
| The server-rendered admin panel (login A-01, 2FA A-02, dashboard, etc.) is
| built in the admin-shell increment (Phase 1D). For now, a minimal status root.
*/

Route::get('/', static fn () => response()->json([
    'app' => config('app.name'),
    'status' => 'ok',
]));

// Public visitor page (no login) — the human-facing target of a shared visitor link. The token in the path
// is the credential; all actions run through the /v1/visit JSON API. Config key visitor.path (default "v").
Route::get('/'.config('domain.visitor.path', 'v').'/{token}', [VisitorPageController::class, 'show'])
    ->where('token', '[A-Za-z0-9_-]+')
    ->name('visitorPage');
