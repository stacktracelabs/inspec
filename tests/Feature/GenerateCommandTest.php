<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Workbench\App\OpenApi\AmbiguousWebhookDocumentation;
use Workbench\App\OpenApi\BroadcastingDocumentation;
use Workbench\App\OpenApi\ControllerDocumentation;
use Workbench\App\OpenApi\DuplicatePublicDocumentation;
use Workbench\App\OpenApi\FilteredRoutesDocumentation;
use Workbench\App\OpenApi\ManualWebhookDocumentation;
use Workbench\App\OpenApi\MissingNamedRouteDocumentation;
use Workbench\App\OpenApi\MissingWebhookDocumentation;
use Workbench\App\OpenApi\NamedWebhookDocumentation;
use Workbench\App\OpenApi\NamelessDocumentation;
use Workbench\App\OpenApi\WithoutBroadcastingDocumentation;
use Workbench\App\OpenApi\WithoutSanctumDocumentation;

afterEach(function () {
    File::deleteDirectory(base_path('inspec-tests'));
});

test('it writes the spec for a documentation class that discovers controller routes', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ControllerDocumentation::class,
    ]);

    $publicOutput = base_path('inspec-tests/openapi/public.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain($publicOutput)
        ->assertSuccessful();

    expect(File::exists($publicOutput))->toBeTrue();

    $document = Yaml::parseFile($publicOutput);

    expect($document['info'])->toMatchArray([
        'title' => 'Public API',
        'description' => 'Generated from documentation class',
        'version' => '2026.04.02',
    ]);

    expect($document['servers'])->toBe([
        [
            'url' => 'https://api.example.com',
            'description' => 'Production',
        ],
        [
            'url' => 'http://localhost:8000/api',
            'description' => 'Local',
        ],
    ]);

    expect($document['paths']['/spec-test']['get']['summary'])->toBe('Generate spec fixture');
});

test('it can generate only the selected api name', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ControllerDocumentation::class,
        ManualWebhookDocumentation::class,
    ]);

    $publicOutput = base_path('inspec-tests/openapi/public.yaml');
    $webhookOutput = base_path('inspec-tests/openapi/webhooks.yaml');

    $this->artisan('inspec:generate', ['--api' => 'webhooks'])
        ->expectsOutputToContain($webhookOutput)
        ->assertSuccessful();

    expect(File::exists($webhookOutput))->toBeTrue()
        ->and(File::exists($publicOutput))->toBeFalse();
});

test('it can generate only the selected documentation class', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ControllerDocumentation::class,
        ManualWebhookDocumentation::class,
    ]);

    $publicOutput = base_path('inspec-tests/openapi/public.yaml');
    $webhookOutput = base_path('inspec-tests/openapi/webhooks.yaml');

    $this->artisan('inspec:generate', ['--api' => ManualWebhookDocumentation::class])
        ->expectsOutputToContain($webhookOutput)
        ->assertSuccessful();

    expect(File::exists($webhookOutput))->toBeTrue()
        ->and(File::exists($publicOutput))->toBeFalse();
});

test('it writes yaml to stdout without rewriting files', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ControllerDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/public.yaml');

    $this->artisan('inspec:generate', [
        '--api' => 'public',
        '--stdout' => true,
    ])
        ->expectsOutputToContain('openapi: 3.0.0')
        ->assertSuccessful();

    expect(File::exists($output))->toBeFalse();
});

test('it fails when stdout would emit more than one spec', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ControllerDocumentation::class,
        ManualWebhookDocumentation::class,
    ]);

    $this->artisan('inspec:generate', ['--stdout' => true])
        ->expectsOutputToContain('The [--stdout] option requires exactly one matched API')
        ->assertExitCode(1);

    expect(File::exists(base_path('inspec-tests/openapi/public.yaml')))->toBeFalse()
        ->and(File::exists(base_path('inspec-tests/openapi/webhooks.yaml')))->toBeFalse();
});

test('it filters routes by final openapi path', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        FilteredRoutesDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/filtered-routes.yaml');

    $this->artisan('inspec:generate', [
        '--api' => 'filtered-routes',
        '--path' => ['^/spec-test$'],
    ])->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect(array_keys($document['paths']))->toBe(['/spec-test']);
});

test('it filters routes by exact laravel route name', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        FilteredRoutesDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/filtered-routes.yaml');

    $this->artisan('inspec:generate', [
        '--api' => 'filtered-routes',
        '--route' => ['webhooks.named'],
    ])->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect(array_keys($document['paths']))->toBe(['/webhooks/named'])
        ->and(array_keys($document['paths']['/webhooks/named']))->toBe(['get', 'post']);
});

test('it filters routes by method case-insensitively', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        FilteredRoutesDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/filtered-routes.yaml');

    $this->artisan('inspec:generate', [
        '--api' => 'filtered-routes',
        '--method' => ['post'],
    ])->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect(array_keys($document['paths']))->toBe(['/webhooks', '/webhooks/named'])
        ->and(array_keys($document['paths']['/webhooks']))->toBe(['post'])
        ->and(array_keys($document['paths']['/webhooks/named']))->toBe(['post']);
});

test('it combines repeated filters as or and filter families as and', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        FilteredRoutesDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/filtered-routes.yaml');

    $this->artisan('inspec:generate', [
        '--api' => 'filtered-routes',
        '--path' => ['^/webhooks$', '^/webhooks/named$'],
        '--method' => ['GET', 'POST'],
    ])->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect(array_keys($document['paths']))->toBe(['/webhooks', '/webhooks/named'])
        ->and(array_keys($document['paths']['/webhooks']))->toBe(['post'])
        ->and(array_keys($document['paths']['/webhooks/named']))->toBe(['get', 'post']);
});

test('it fails when a path filter is not a valid regex', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ControllerDocumentation::class,
    ]);

    $this->artisan('inspec:generate', [
        '--api' => 'public',
        '--path' => ['['],
    ])
        ->expectsOutputToContain('The path filter [[] is not a valid regex')
        ->assertExitCode(1);
});

test('it documents a manually defined route and infers middleware-based security', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ManualWebhookDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/webhooks.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain($output)
        ->assertSuccessful();

    expect(File::exists($output))->toBeTrue();

    $document = Yaml::parseFile($output);

    expect($document['paths']['/webhooks']['post']['summary'])->toBe('Receive webhooks')
        ->and($document['paths']['/webhooks']['post']['security'])->toBe([
            [
                'bearerAuth' => [],
            ],
        ])
        ->and($document['components']['securitySchemes']['bearerAuth'])->toBe([
            'type' => 'http',
            'scheme' => 'bearer',
        ]);
});

test('it can opt out of sanctum security inference and bearer auth registration', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        WithoutSanctumDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/without-sanctum.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain($output)
        ->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect($document['paths']['/webhooks']['post']['summary'])->toBe('Receive webhooks without sanctum docs')
        ->and($document['paths']['/webhooks']['post']['security'] ?? null)->toBeNull()
        ->and($document['components']['securitySchemes']['bearerAuth'] ?? null)->toBeNull();
});

test('it auto-documents registered broadcasting routes by default', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        BroadcastingDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/broadcasting.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain($output)
        ->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect(array_keys($document['paths']))->toContain('/broadcasting/auth', '/broadcasting/user-auth')
        ->and(array_keys($document['paths']))->not->toContain('/api/broadcasting/auth', '/api/broadcasting/user-auth')
        ->and(array_keys($document['paths']['/broadcasting/auth']))->toBe(['get', 'post'])
        ->and(array_keys($document['paths']['/broadcasting/user-auth']))->toBe(['get', 'post'])
        ->and($document['paths']['/broadcasting/auth']['post']['summary'])->toBe('Authorize Websocket channel')
        ->and($document['paths']['/broadcasting/user-auth']['post']['summary'])->toBe('Authenticate Websocket user')
        ->and($document['paths']['/broadcasting/auth']['post']['security'])->toBe([
            [
                'bearerAuth' => [],
            ],
        ]);
});

test('it can opt out of broadcasting auto documentation', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        WithoutBroadcastingDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/without-broadcasting.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain($output)
        ->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect(array_keys($document['paths']))->toBe(['/webhooks/prefixed'])
        ->and($document['paths']['/broadcasting/auth'] ?? null)->toBeNull()
        ->and($document['paths']['/broadcasting/user-auth'] ?? null)->toBeNull();
});

test('it filters auto documented broadcasting routes by cli path', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        BroadcastingDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/broadcasting.yaml');

    $this->artisan('inspec:generate', [
        '--api' => 'broadcasting',
        '--path' => ['^/broadcasting/auth$'],
    ])->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect(array_keys($document['paths']))->toBe(['/broadcasting/auth']);
});

test('it filters auto documented broadcasting routes by cli method', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        BroadcastingDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/broadcasting.yaml');

    $this->artisan('inspec:generate', [
        '--api' => 'broadcasting',
        '--method' => ['get'],
    ])->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect(array_keys($document['paths']))->toBe(['/broadcasting/auth', '/broadcasting/user-auth'])
        ->and(array_keys($document['paths']['/broadcasting/auth']))->toBe(['get'])
        ->and(array_keys($document['paths']['/broadcasting/user-auth']))->toBe(['get']);
});

test('it filters auto documented broadcasting routes by cli route name', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        BroadcastingDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/broadcasting.yaml');

    $this->artisan('inspec:generate', [
        '--api' => 'broadcasting',
        '--route' => ['webhooks.named'],
    ])->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect(array_key_exists('paths', $document))->toBeFalse();
});

test('it documents a named route using its real methods', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        NamedWebhookDocumentation::class,
    ]);

    $output = base_path('inspec-tests/openapi/named-webhooks.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain($output)
        ->assertSuccessful();

    $document = Yaml::parseFile($output);

    expect($document['paths']['/webhooks/named']['get']['summary'])->toBe('Named webhook route')
        ->and($document['paths']['/webhooks/named']['post']['summary'])->toBe('Named webhook route');
});

test('it fails when documentation does not set an api name', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        NamelessDocumentation::class,
    ]);

    $this->artisan('inspec:generate')
        ->expectsOutputToContain('must define an API name')
        ->assertExitCode(1);
});

test('it fails when the api selector does not match any configured api or documentation', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ControllerDocumentation::class,
    ]);

    $this->artisan('inspec:generate', ['--api' => 'missing'])
        ->expectsOutputToContain('did not match any configured API or documentation class')
        ->assertExitCode(1);
});

test('it fails when the api selector is ambiguous', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ControllerDocumentation::class,
        DuplicatePublicDocumentation::class,
    ]);

    $this->artisan('inspec:generate', ['--api' => 'public'])
        ->expectsOutputToContain('The [--api] selector [public] is ambiguous')
        ->assertExitCode(1);
});

test('it fails when a manual route does not exist', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        MissingWebhookDocumentation::class,
    ]);

    $this->artisan('inspec:generate')
        ->expectsOutputToContain('Unable to resolve route [POST /missing-webhook]')
        ->assertExitCode(1);
});

test('it fails when a manual route selector is ambiguous', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        AmbiguousWebhookDocumentation::class,
    ]);

    $this->artisan('inspec:generate')
        ->expectsOutputToContain('The route [POST /webhooks/ambiguous] is ambiguous')
        ->assertExitCode(1);
});

test('it fails when a named route does not exist', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        MissingNamedRouteDocumentation::class,
    ]);

    $this->artisan('inspec:generate')
        ->expectsOutputToContain('Unable to resolve route [webhooks.missing] by name')
        ->assertExitCode(1);
});

test('it fails when no docs are configured', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', []);

    $this->artisan('inspec:generate')
        ->expectsOutputToContain('The [inspec.docs] configuration is empty')
        ->assertExitCode(1);
});
