<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Generates a Postman collection and a Bruno collection from the built OpenAPI spec.
 *
 *   php artisan docs:generate
 *
 * Reads docs/openapi/openapi.json (built by docs/openapi/build-openapi.mjs — run that first
 * after editing the YAML) and emits:
 *   - docs/postman/Salam.postman_collection.json   (Postman 2.1)
 *   - docs/bruno/                                   (Bruno collection: bruno.json + .bru files)
 *
 * Both use {{baseUrl}} + {{jwt}} / {{adminJwt}} variables so the user only fills in a token.
 */
class GenerateApiDocs extends Command
{
    protected $signature = 'docs:generate';

    protected $description = 'Generate Postman + Bruno collections from the OpenAPI spec';

    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /** @var array<string,mixed> */
    private array $spec = [];

    public function handle(): int
    {
        $specPath = base_path('docs/openapi/openapi.json');
        if (! File::exists($specPath)) {
            $this->error('docs/openapi/openapi.json not found. Build it first: node docs/openapi/build-openapi.mjs');

            return self::FAILURE;
        }

        $this->spec = json_decode(File::get($specPath), true);
        $baseUrl = $this->spec['servers'][0]['url'] ?? 'https://api.salamheyetimiz.com';

        $this->writePostman($baseUrl);
        $this->writeBruno($baseUrl);

        $this->info('Generated Postman + Bruno collections from openapi.json.');

        return self::SUCCESS;
    }

    // ---- Postman -----------------------------------------------------------

    private function writePostman(string $baseUrl): void
    {
        $folders = [];
        foreach ($this->operations() as $op) {
            $folders[$op['tag']][] = $this->postmanRequest($op);
        }

        $items = [];
        foreach ($folders as $tag => $requests) {
            $items[] = ['name' => $tag, 'item' => $requests];
        }

        $collection = [
            'info' => [
                'name' => ($this->spec['info']['title'] ?? 'Salam API').' v'.($this->spec['info']['version'] ?? '1'),
                'description' => 'Auto-generated from openapi.json. Set {{jwt}} (mobile) / {{adminJwt}} (admin) after login.',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                ['key' => 'baseUrl', 'value' => $baseUrl],
                ['key' => 'jwt', 'value' => ''],
                ['key' => 'adminJwt', 'value' => ''],
            ],
            'item' => $items,
        ];

        File::ensureDirectoryExists(base_path('docs/postman'));
        File::put(
            base_path('docs/postman/Salam.postman_collection.json'),
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    /**
     * @param  array<string,mixed>  $op
     * @return array<string,mixed>
     */
    private function postmanRequest(array $op): array
    {
        $segments = array_values(array_filter(explode('/', $op['path'])));
        $pathParts = array_map(static fn (string $s): string => Str::startsWith($s, '{') ? ':'.trim($s, '{}') : $s, $segments);
        $raw = '{{baseUrl}}/'.implode('/', $pathParts);

        $headers = [];
        $variables = [];
        $queries = [];
        foreach ($op['parameters'] as $p) {
            match ($p['in'] ?? '') {
                'header' => $headers[] = ['key' => $p['name'], 'value' => '', 'description' => $p['description'] ?? ''],
                'path' => $variables[] = ['key' => $p['name'], 'value' => '1'],
                'query' => $queries[] = ['key' => $p['name'], 'value' => '', 'disabled' => true],
                default => null,
            };
        }

        $url = ['raw' => $raw, 'host' => ['{{baseUrl}}'], 'path' => $pathParts];
        if ($queries !== []) {
            $url['query'] = $queries;
            $url['raw'] = $raw.'?'.implode('&', array_map(static fn ($q) => $q['key'].'=', $queries));
        }
        if ($variables !== []) {
            $url['variable'] = $variables;
        }

        $request = [
            'method' => strtoupper($op['method']),
            'header' => $headers,
            'url' => $url,
            'description' => $op['description'],
        ];

        $auth = $this->authToken($op['security']);
        if ($auth !== null) {
            $request['auth'] = ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => $auth, 'type' => 'string']]];
        } else {
            $request['auth'] = ['type' => 'noauth'];
        }

        if ($op['body'] !== null) {
            array_unshift($request['header'], ['key' => 'Content-Type', 'value' => 'application/json']);
            $request['body'] = [
                'mode' => 'raw',
                'raw' => json_encode($op['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }

        return ['name' => $op['summary'], 'request' => $request];
    }

    // ---- Bruno -------------------------------------------------------------

    private function writeBruno(string $baseUrl): void
    {
        $root = base_path('docs/bruno');
        File::deleteDirectory($root);
        File::ensureDirectoryExists($root);

        File::put($root.'/bruno.json', json_encode([
            'version' => '1',
            'name' => 'Salam Həyətimiz API',
            'type' => 'collection',
            'ignore' => ['node_modules', '.git'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        File::ensureDirectoryExists($root.'/environments');
        File::put($root.'/environments/Production.bru', $this->brunoEnv($baseUrl));

        $seq = [];
        foreach ($this->operations() as $op) {
            $folder = $root.'/'.$this->slugFolder($op['tag']);
            File::ensureDirectoryExists($folder);
            $seq[$op['tag']] = ($seq[$op['tag']] ?? 0) + 1;
            $name = Str::limit(Str::slug($op['summary'] ?: $op['operationId']), 60, '');
            File::put($folder.'/'.$op['method'].'-'.$name.'.bru', $this->brunoRequest($op, $seq[$op['tag']]));
        }
    }

    /** @param array<string,mixed> $op */
    private function brunoRequest(array $op, int $seq): string
    {
        $segments = array_map(
            static fn (string $s): string => Str::startsWith($s, '{') ? ':'.trim($s, '{}') : $s,
            array_values(array_filter(explode('/', $op['path']))),
        );
        $url = '{{baseUrl}}/'.implode('/', $segments);
        $method = $op['method'];
        $bodyMode = $op['body'] !== null ? 'json' : 'none';
        $authMode = $this->authToken($op['security']) !== null ? 'bearer' : 'none';

        $out = "meta {\n  name: ".$this->brunoEscape($op['summary'])."\n  type: http\n  seq: {$seq}\n}\n\n";
        $out .= "{$method} {\n  url: {$url}\n  body: {$bodyMode}\n  auth: {$authMode}\n}\n";

        $headerLines = [];
        if ($op['body'] !== null) {
            $headerLines[] = '  Content-Type: application/json';
        }
        foreach ($op['parameters'] as $p) {
            if (($p['in'] ?? '') === 'header') {
                $headerLines[] = '  '.$p['name'].': ';
            }
        }
        if ($headerLines !== []) {
            $out .= "\nheaders {\n".implode("\n", $headerLines)."\n}\n";
        }

        if ($authMode === 'bearer') {
            $token = $this->authToken($op['security']);
            $out .= "\nauth:bearer {\n  token: {$token}\n}\n";
        }
        if ($op['body'] !== null) {
            $json = json_encode($op['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $out .= "\nbody:json {\n".$json."\n}\n";
        }
        if (($op['description'] ?? '') !== '') {
            $out .= "\ndocs {\n  ".$this->brunoEscape(Str::limit($op['description'], 200))."\n}\n";
        }

        return $out;
    }

    private function brunoEnv(string $baseUrl): string
    {
        return "vars {\n  baseUrl: {$baseUrl}\n  jwt: \n  adminJwt: \n}\n";
    }

    private function brunoEscape(string $s): string
    {
        return trim(str_replace(["\n", "\r"], ' ', $s));
    }

    private function slugFolder(string $tag): string
    {
        return Str::of($tag)->replace('/', '-')->slug()->value() ?: 'general';
    }

    // ---- shared spec walk --------------------------------------------------

    /**
     * Flattened list of operations.
     *
     * @return array<int,array<string,mixed>>
     */
    private function operations(): array
    {
        $ops = [];
        foreach ($this->spec['paths'] as $path => $item) {
            $pathParams = $item['parameters'] ?? [];
            foreach ($item as $method => $op) {
                if (! in_array($method, self::METHODS, true)) {
                    continue;
                }
                $params = array_merge($pathParams, $op['parameters'] ?? []);
                $ops[] = [
                    'path' => $path,
                    'method' => $method,
                    'tag' => $op['tags'][0] ?? 'General',
                    'summary' => $op['summary'] ?? ($op['operationId'] ?? $method.' '.$path),
                    'operationId' => $op['operationId'] ?? Str::camel($method.' '.$path),
                    'description' => $op['description'] ?? '',
                    'parameters' => array_map(fn ($p) => $this->resolve($p), $params),
                    'security' => $op['security'] ?? $this->spec['security'] ?? [],
                    'body' => $this->bodyExample($op),
                ];
            }
        }

        return $ops;
    }

    /** @param array<string,mixed> $op */
    private function bodyExample(array $op): mixed
    {
        $schema = $op['requestBody']['content']['application/json']['schema'] ?? null;

        return $schema !== null ? $this->example($schema, 0) : null;
    }

    /**
     * Shallow example generator (depth-bounded) for request bodies.
     *
     * @param  array<string,mixed>  $schema
     */
    private function example(array $schema, int $depth): mixed
    {
        if ($depth > 3) {
            return null;
        }
        $schema = $this->resolve($schema);

        if (isset($schema['example'])) {
            return $schema['example'];
        }
        if (isset($schema['allOf'])) {
            $merged = [];
            foreach ($schema['allOf'] as $part) {
                $ex = $this->example($part, $depth + 1);
                if (is_array($ex)) {
                    $merged = array_merge($merged, $ex);
                }
            }

            return $merged;
        }
        if (($schema['type'] ?? null) === 'array') {
            return [$this->example($schema['items'] ?? [], $depth + 1)];
        }
        if (isset($schema['properties'])) {
            $obj = [];
            foreach ($schema['properties'] as $name => $prop) {
                $obj[$name] = $this->example($prop, $depth + 1);
            }

            return $obj;
        }

        return match ($schema['type'] ?? 'string') {
            'integer', 'number' => 0,
            'boolean' => false,
            'object' => new \stdClass(),
            default => '',
        };
    }

    /**
     * Resolve a one-level local $ref against components/schemas (or any pointer).
     *
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private function resolve(array $node): array
    {
        if (! isset($node['$ref'])) {
            return $node;
        }
        $ref = ltrim($node['$ref'], '#/');
        $cur = $this->spec;
        foreach (explode('/', $ref) as $seg) {
            if (! isset($cur[$seg])) {
                return $node;
            }
            $cur = $cur[$seg];
        }

        return is_array($cur) ? $cur : $node;
    }

    /**
     * @param  array<int,mixed>  $security
     */
    private function authToken(array $security): ?string
    {
        if ($security === []) {
            return null; // explicit `security: []` → public
        }
        foreach ($security as $req) {
            if ($req === []) {
                return null; // explicit public
            }
            if (array_key_exists('adminBearerAuth', $req)) {
                return '{{adminJwt}}';
            }
            if (array_key_exists('bearerAuth', $req)) {
                return '{{jwt}}';
            }
        }

        return null;
    }
}
