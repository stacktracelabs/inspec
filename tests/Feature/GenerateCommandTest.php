<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

afterEach(function () {
    File::deleteDirectory(base_path('inspec-tests'));
});

test('it writes separate specs for each configured api', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.apis', [
        'public' => [
            'title' => 'Public API',
            'description' => 'Generated from tests',
            'version' => '2026.04.01',
            'servers' => [
                'Production' => 'https://api.example.com',
                'Local' => 'http://localhost:8000',
            ],
            'paths' => [
                \Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'),
            ],
        ],
        'admin' => [
            'title' => 'Admin API',
            'description' => 'Administrative endpoints',
            'version' => '2026.04.02',
            'servers' => [
                'Backoffice' => 'https://admin.example.com',
            ],
            'paths' => [
                \Orchestra\Testbench\workbench_path('app/Http/Controllers/Admin'),
            ],
        ],
    ]);

    $publicOutput = base_path('inspec-tests/openapi/public.yaml');
    $adminOutput = base_path('inspec-tests/openapi/admin.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain($publicOutput)
        ->expectsOutputToContain($adminOutput)
        ->assertSuccessful();

    expect(File::exists($publicOutput))->toBeTrue()
        ->and(File::exists($adminOutput))->toBeTrue();

    $publicDocument = Yaml::parseFile($publicOutput);
    $adminDocument = Yaml::parseFile($adminOutput);

    expect($publicDocument['info'])->toMatchArray([
        'title' => 'Public API',
        'description' => 'Generated from tests',
        'version' => '2026.04.01',
    ]);

    expect($publicDocument['servers'])->toBe([
        [
            'url' => 'https://api.example.com',
            'description' => 'Production',
        ],
        [
            'url' => 'http://localhost:8000',
            'description' => 'Local',
        ],
    ]);

    expect($publicDocument['paths']['/spec-test']['get']['summary'])->toBe('Generate spec fixture');

    expect($adminDocument['info'])->toMatchArray([
        'title' => 'Admin API',
        'description' => 'Administrative endpoints',
        'version' => '2026.04.02',
    ]);

    expect($adminDocument['servers'])->toBe([
        [
            'url' => 'https://admin.example.com',
            'description' => 'Backoffice',
        ],
    ]);

    expect($adminDocument['paths']['/admin/spec-test']['get']['summary'])->toBe('Generate admin spec fixture');
});

test('it can generate a single configured api', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.apis', [
        'public' => [
            'paths' => [
                \Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'),
            ],
        ],
        'admin' => [
            'paths' => [
                \Orchestra\Testbench\workbench_path('app/Http/Controllers/Admin'),
            ],
        ],
    ]);

    $publicOutput = base_path('inspec-tests/openapi/public.yaml');
    $adminOutput = base_path('inspec-tests/openapi/admin.yaml');

    $this->artisan('inspec:generate', ['api' => 'admin'])
        ->expectsOutputToContain($adminOutput)
        ->assertSuccessful();

    expect(File::exists($adminOutput))->toBeTrue()
        ->and(File::exists($publicOutput))->toBeFalse();
});

test('it supports the legacy single-api configuration', function () {
    config()->set('inspec.apis', null);
    config()->set('inspec.title', 'Legacy API');
    config()->set('inspec.description', 'Legacy configuration');
    config()->set('inspec.version', '2026.04.03');
    config()->set('inspec.servers', [
        'Legacy' => 'https://legacy.example.com',
    ]);
    config()->set('inspec.paths', [
        \Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'),
    ]);
    config()->set('inspec.output', 'inspec-tests/legacy/openapi.yaml');

    $output = base_path('inspec-tests/legacy/openapi.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain($output)
        ->assertSuccessful();

    expect(File::exists($output))->toBeTrue();

    $document = Yaml::parseFile($output);

    expect($document['info'])->toMatchArray([
        'title' => 'Legacy API',
        'description' => 'Legacy configuration',
        'version' => '2026.04.03',
    ]);
});

test('it fails when a configured controller path does not exist', function () {
    config()->set('inspec.output', 'inspec-tests/openapi');
    config()->set('inspec.apis', [
        'public' => [
            'paths' => [
                \Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'),
            ],
        ],
        'missing' => [
            'paths' => [
                base_path('inspec-tests/missing'),
            ],
        ],
    ]);

    $this->artisan('inspec:generate')
        ->expectsOutputToContain('does not exist or is not a directory')
        ->assertExitCode(1);

    expect(File::exists(base_path('inspec-tests/openapi/public.yaml')))->toBeFalse()
        ->and(File::exists(base_path('inspec-tests/openapi/missing.yaml')))->toBeFalse();
});
