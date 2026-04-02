<?php


namespace StackTrace\Inspec\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use StackTrace\Inspec\Generator;
use Throwable;

class Generate extends Command
{
    protected $signature = 'inspec:generate {api?}';

    protected $description = 'Generate the configured OpenAPI spec files.';

    public function handle(): int
    {
        $apis = $this->apis();

        if ($apis === []) {
            $this->error('The [inspec.apis] configuration is empty.');

            return self::FAILURE;
        }

        $selectedApi = $this->selectedApi($apis);

        if (is_null($selectedApi) && $this->argument('api')) {
            $api = trim((string) $this->argument('api'));
            $this->error("The configured API [{$api}] does not exist.");

            return self::FAILURE;
        }

        if (! is_null($selectedApi)) {
            $apis = [
                $selectedApi => $apis[$selectedApi],
            ];
        }

        $specifications = [];
        $resolvedOutputs = [];

        foreach ($apis as $name => $config) {
            $paths = $this->paths($config['paths'] ?? []);

            if ($paths === []) {
                $this->error("The configured controller paths for API [{$name}] are empty.");

                return self::FAILURE;
            }

            foreach ($paths as $path) {
                if (! is_dir($path)) {
                    $this->error("The configured controller path [{$path}] for API [{$name}] does not exist or is not a directory.");

                    return self::FAILURE;
                }
            }

            $output = $this->resolveApiOutputPath($name, $config);

            if (is_null($output)) {
                $this->error("The output path for API [{$name}] is not configured.");

                return self::FAILURE;
            }

            if (array_key_exists($output, $resolvedOutputs)) {
                $existing = $resolvedOutputs[$output];

                $this->error("The configured APIs [{$existing}] and [{$name}] resolve to the same output path [{$output}].");

                return self::FAILURE;
            }

            $resolvedOutputs[$output] = $name;

            try {
                $yaml = new Generator(
                    title: $this->stringConfig($config, 'title', config('inspec.title', config('app.name', 'Laravel'))),
                    description: $this->stringConfig($config, 'description', config('inspec.description', '')),
                    version: $this->stringConfig($config, 'version', config('inspec.version', '1.0.0')),
                    servers: $this->servers($config['servers'] ?? config('inspec.servers', [])),
                    paths: $paths,
                )->generate()->toYaml();
            } catch (Throwable $e) {
                $this->error("Unable to generate API [{$name}]: {$e->getMessage()}");

                return self::FAILURE;
            }

            $specifications[$name] = [
                'output' => $output,
                'yaml' => $yaml,
            ];
        }

        try {
            foreach ($specifications as $name => $specification) {
                File::ensureDirectoryExists(dirname($specification['output']));
                File::put($specification['output'], $specification['yaml']);

                $this->info("OpenAPI spec for [{$name}] written to [{$specification['output']}].");
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function apis(): array
    {
        $apis = config('inspec.apis');

        if (is_array($apis) && $apis !== []) {
            return collect($apis)
                ->filter(fn (mixed $config, mixed $name) => is_string($name) && trim($name) !== '' && is_array($config))
                ->mapWithKeys(fn (array $config, string $name) => [trim($name) => $config])
                ->all();
        }

        return [
            'api' => [
                'title' => config('inspec.title', config('app.name', 'Laravel')),
                'description' => config('inspec.description', ''),
                'version' => config('inspec.version', '1.0.0'),
                'servers' => config('inspec.servers', []),
                'paths' => config('inspec.paths', []),
                'output' => config('inspec.output', 'openapi.yaml'),
            ],
        ];
    }

    protected function selectedApi(array $apis): ?string
    {
        $api = trim((string) $this->argument('api'));

        if ($api === '') {
            return null;
        }

        return array_key_exists($api, $apis) ? $api : null;
    }

    protected function paths(mixed $paths): array
    {
        return collect(Arr::wrap($paths))
            ->filter(fn (mixed $path) => is_string($path) && trim($path) !== '')
            ->values()
            ->all();
    }

    protected function servers(mixed $servers): array
    {
        return collect(Arr::wrap($servers))
            ->filter(fn (mixed $url, mixed $description) => is_string($description) && trim((string) $description) !== '' && is_string($url) && trim($url) !== '')
            ->map(fn (string $url) => trim($url))
            ->all();
    }

    protected function resolveApiOutputPath(string $name, array $config): ?string
    {
        $output = $config['output'] ?? config('inspec.output', 'openapi');

        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        $output = trim($output);

        if ($this->isYamlPath($output)) {
            return $this->resolveOutputPath($output);
        }

        $filename = Str::slug($name);

        if ($filename === '') {
            $filename = 'openapi';
        }

        return $this->resolveOutputPath(rtrim($output, '/\\').DIRECTORY_SEPARATOR.$filename.'.yaml');
    }

    protected function resolveOutputPath(string $output): string
    {
        if ($this->isAbsolutePath($output)) {
            return $output;
        }

        return base_path($output);
    }

    protected function isYamlPath(string $path): bool
    {
        return preg_match('/\.(?:yaml|yml)$/i', $path) === 1;
    }

    protected function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
    }

    protected function stringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value === '' ? $default : $value;
    }
}
