<?php

use App\Http\Docs\Controllers\ApiDocsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Documentation — prefix /api (gated by docs.access)
|--------------------------------------------------------------------------
| Disjoint from the /v1 and /admin/v1 API surfaces. Serves Swagger UI, ReDoc,
| and the raw OpenAPI 3.1 spec. In production the whole surface is behind HTTP
| Basic Auth (DocsAccess) so anonymous users cannot inspect the API; the JWT
| "Try it out" flow is independent (token entered in Swagger's Authorize box).
|
| Versioning is first-class: /api/openapi.json serves the default version and
| /api/v1/openapi.json (future /api/v2/...) the explicit one.
*/

Route::middleware('docs.access')->group(function (): void {
    Route::get('docs', [ApiDocsController::class, 'swagger'])->name('apiDocs');
    Route::get('docs/swagger', [ApiDocsController::class, 'swagger'])->name('apiDocsSwagger');
    Route::get('redoc', [ApiDocsController::class, 'redoc'])->name('apiRedoc');
    Route::get('openapi.json', [ApiDocsController::class, 'openapiJson'])->name('apiOpenapiJson');
    Route::get('openapi.yaml', [ApiDocsController::class, 'openapiYaml'])->name('apiOpenapiYaml');
    Route::get('postman.json', [ApiDocsController::class, 'postman'])->name('apiPostman');

    // Version-scoped aliases (API versioning structure).
    Route::get('{version}/openapi.json', [ApiDocsController::class, 'openapiJson'])
        ->whereIn('version', ['v1', 'v2'])->name('apiOpenapiJsonVersioned');
    Route::get('{version}/docs', [ApiDocsController::class, 'swagger'])
        ->whereIn('version', ['v1', 'v2'])->name('apiDocsVersioned');
    Route::get('{version}/redoc', [ApiDocsController::class, 'redoc'])
        ->whereIn('version', ['v1', 'v2'])->name('apiRedocVersioned');
});
