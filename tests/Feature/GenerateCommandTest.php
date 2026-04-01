<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

afterEach(function () {
    File::deleteDirectory(base_path('inspec-tests'));
});

test('it writes the spec to the configured relative output path', function () {
    config()->set('inspec.title', 'Test API');
    config()->set('inspec.description', 'Generated from tests');
    config()->set('inspec.version', '2026.04.01');
    config()->set('inspec.servers', [
        'Production' => 'https://api.example.com',
        'Local' => 'http://localhost:8000',
    ]);
    config()->set('inspec.paths', [
        \Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'),
    ]);
    config()->set('inspec.output', 'inspec-tests/configured/openapi.yaml');

    $output = base_path('inspec-tests/configured/openapi.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain($output)
        ->assertSuccessful();

    expect(File::exists($output))->toBeTrue();

    $document = Yaml::parseFile($output);

    expect($document['info'])->toMatchArray([
        'title' => 'Test API',
        'description' => 'Generated from tests',
        'version' => '2026.04.01',
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

test('it prefers the explicit output argument over configuration', function () {
    config()->set('inspec.paths', [
        \Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'),
    ]);
    config()->set('inspec.output', 'inspec-tests/configured/openapi.yaml');

    $configuredOutput = base_path('inspec-tests/configured/openapi.yaml');
    $manualOutput = base_path('inspec-tests/manual/nested/openapi.yaml');

    $this->artisan('inspec:generate', ['output' => 'inspec-tests/manual/nested/openapi.yaml'])
        ->expectsOutputToContain($manualOutput)
        ->assertSuccessful();

    expect(File::exists($manualOutput))->toBeTrue()
        ->and(File::exists($configuredOutput))->toBeFalse();
});

test('it fails when a configured controller path does not exist', function () {
    config()->set('inspec.paths', [
        base_path('inspec-tests/missing'),
    ]);
    config()->set('inspec.output', 'inspec-tests/missing/openapi.yaml');

    $this->artisan('inspec:generate')
        ->expectsOutputToContain('does not exist or is not a directory')
        ->assertExitCode(1);

    expect(File::exists(base_path('inspec-tests/missing/openapi.yaml')))->toBeFalse();
});
