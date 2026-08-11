<?php

namespace App\Http\Docs\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the API documentation surface (R-DOCS):
 *   GET /api/docs            → Swagger UI (default version)
 *   GET /api/docs/swagger    → Swagger UI
 *   GET /api/redoc           → ReDoc
 *   GET /api/openapi.json    → merged OpenAPI 3.1 spec (JSON)
 *   GET /api/openapi.yaml    → source spec (YAML)
 *   GET /api/{version}/...   → version-scoped equivalents (e.g. /api/v1/openapi.json)
 *
 * The spec is pre-built by docs/openapi/build-openapi.mjs (v1.yaml + v1.extra.yaml → openapi.json),
 * so this controller never parses YAML at runtime. Access is gated by the DocsAccess middleware.
 */
class ApiDocsController
{
    public function openapiJson(Request $request, ?string $version = null): Response
    {
        $path = $this->specPath($version);
        if ($path === null || ! File::exists($path)) {
            return response()->json(['error' => ['code' => 'spec_unavailable', 'message' => 'OpenAPI spec not built']], 503);
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function postman(): Response
    {
        $path = base_path('docs/postman/Salam.postman_collection.json');
        if (! File::exists($path)) {
            return response('collection not generated', 404);
        }

        return response(File::get($path), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="Salam.postman_collection.json"',
        ]);
    }

    public function openapiYaml(): Response
    {
        $path = base_path('docs/openapi/v1.yaml');
        if (! File::exists($path)) {
            return response('spec not found', 404);
        }

        return response(File::get($path), 200, ['Content-Type' => 'application/yaml; charset=utf-8']);
    }

    public function swagger(Request $request, ?string $version = null): Response
    {
        $specUrl = $this->specUrl($version);
        $cdn = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@'.config('docs.swagger_ui_version').'/';
        $title = e(config('docs.title')).' — Swagger UI';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <link rel="stylesheet" href="{$cdn}swagger-ui.css">
  <style>body{margin:0;background:#fafafa}.topbar{display:none}</style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="{$cdn}swagger-ui-bundle.js"></script>
  <script src="{$cdn}swagger-ui-standalone-preset.js"></script>
  <script>
    window.ui = SwaggerUIBundle({
      url: "{$specUrl}",
      dom_id: "#swagger-ui",
      deepLinking: true,
      presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
      layout: "StandaloneLayout",
      persistAuthorization: true,
      tryItOutEnabled: true,
      filter: true,
      docExpansion: "none",
    });
  </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function redoc(Request $request, ?string $version = null): Response
    {
        $specUrl = $this->specUrl($version);
        $cdn = 'https://cdn.jsdelivr.net/npm/redoc@'.config('docs.redoc_version').'/bundles/redoc.standalone.js';
        $title = e(config('docs.title')).' — ReDoc';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$title}</title>
  <style>body{margin:0;padding:0}</style>
</head>
<body>
  <redoc spec-url="{$specUrl}" hide-download-button></redoc>
  <script src="{$cdn}"></script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** Resolve the on-disk spec path for a version (defaults to config('docs.default_version')). */
    private function specPath(?string $version): ?string
    {
        $version = $version ?: config('docs.default_version', 'v1');
        $rel = config('docs.specs.'.$version);

        return $rel !== null ? base_path($rel) : null;
    }

    /** Build the public spec URL for the rendered UIs. */
    private function specUrl(?string $version): string
    {
        return $version !== null && $version !== ''
            ? url('/api/'.$version.'/openapi.json')
            : url('/api/openapi.json');
    }
}
