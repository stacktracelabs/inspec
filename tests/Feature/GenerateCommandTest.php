<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Workbench\App\OpenApi\AmbiguousWebhookDocumentation;
use Workbench\App\OpenApi\ControllerDocumentation;
use Workbench\App\OpenApi\ManualWebhookDocumentation;
use Workbench\App\OpenApi\MissingNamedRouteDocumentation;
use Workbench\App\OpenApi\MissingWebhookDocumentation;
use Workbench\App\OpenApi\NamedWebhookDocumentation;
use Workbench\App\OpenApi\NamelessDocumentation;

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
            'url' => 'http://localhost:8000',
            'description' => 'Local',
        ],
    ]);

    expect($document['paths']['/spec-test']['get']['summary'])->toBe('Generate spec fixture');
});

test('it can generate only the selected api', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.docs', [
        ControllerDocumentation::class,
        ManualWebhookDocumentation::class,
    ]);

    $publicOutput = base_path('inspec-tests/openapi/public.yaml');
    $webhookOutput = base_path('inspec-tests/openapi/webhooks.yaml');

    $this->artisan('inspec:generate', ['api' => 'webhooks'])
        ->expectsOutputToContain($webhookOutput)
        ->assertSuccessful();

    expect(File::exists($webhookOutput))->toBeTrue()
        ->and(File::exists($publicOutput))->toBeFalse();
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
        ]);
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
