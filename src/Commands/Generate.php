<?php


namespace StackTrace\Inspec\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;
use Throwable;

class Generate extends Command
{
    protected $signature = 'inspec:generate
        {--api= : Generate one configured API by its name or documentation class}
        {--stdout : Write the generated YAML to standard output instead of a file}
        {--path=* : Regex filter for final generated paths after any Api prefix stripping}
        {--route=* : Exact Laravel route-name filter}
        {--method=* : HTTP method filter}';

    protected $description = 'Generate the configured OpenAPI spec files.';

    public function handle(): int
    {
        $docs = $this->docs();

        if ($docs === []) {
            $this->error('The [inspec.docs] configuration is empty.');

            return self::FAILURE;
        }

        $records = [];

        foreach ($docs as $documentationClass) {
            try {
                $documentation = $this->laravel->make($documentationClass);
            } catch (Throwable $e) {
                $this->error("Unable to resolve documentation [{$documentationClass}]: {$e->getMessage()}");

                return self::FAILURE;
            }

            if (! $documentation instanceof Documentation) {
                $this->error("The configured documentation [{$documentationClass}] must extend [".Documentation::class."].");

                return self::FAILURE;
            }

            try {
                $api = new Api();
                $documentation->build($api);
            } catch (Throwable $e) {
                $this->error("Unable to build documentation [{$documentationClass}]: {$e->getMessage()}");

                return self::FAILURE;
            }

            $name = $api->getName();

            if (! is_string($name) || trim($name) === '') {
                $this->error("The documentation [{$documentationClass}] must define an API name.");

                return self::FAILURE;
            }

            $name = trim($name);

            $records[] = [
                'api' => $api,
                'class' => $documentationClass,
                'name' => $name,
            ];
        }

        $records = $this->selectRecords($records);

        if ($records === null) {
            return self::FAILURE;
        }

        $writeToStdout = (bool) $this->option('stdout');

        if ($writeToStdout && count($records) !== 1) {
            $this->error('The [--stdout] option requires exactly one matched API.');

            return self::FAILURE;
        }

        $outputDirectory = null;

        if (! $writeToStdout) {
            $outputDirectory = $this->outputDirectory();

            if (is_null($outputDirectory)) {
                $this->error('The [inspec.output] configuration is empty.');

                return self::FAILURE;
            }
        }

        $specifications = [];

        try {
            foreach ($records as $record) {
                $api = $record['api'];
                $name = $record['name'];

                $this->applyFilters($api);

                $yaml = $api->toOpenAPI()->toYaml();

                if ($writeToStdout) {
                    $specifications[] = [
                        'yaml' => $yaml,
                    ];

                    continue;
                }

                if (array_key_exists($name, $specifications)) {
                    $this->error("The API [{$name}] is defined by more than one documentation class.");

                    return self::FAILURE;
                }

                $specifications[$name] = [
                    'output' => $this->resolveOutputPath($outputDirectory, $name),
                    'yaml' => $yaml,
                ];
            }

            if ($writeToStdout) {
                $this->output->write($specifications[0]['yaml']);

                return self::SUCCESS;
            }

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

    protected function selectRecords(array $records): ?array
    {
        $selector = trim((string) $this->option('api'));

        if ($selector === '') {
            return $records;
        }

        $matches = collect($records)
            ->filter(fn (array $record) => $record['name'] === $selector || $record['class'] === $selector)
            ->values();

        if ($matches->isEmpty()) {
            $this->error("The [--api] selector [{$selector}] did not match any configured API or documentation class.");

            return null;
        }

        if ($matches->count() > 1) {
            $this->error("The [--api] selector [{$selector}] is ambiguous.");

            return null;
        }

        return $matches->all();
    }

    protected function applyFilters(Api $api): void
    {
        $pathFilters = $this->pathFilters();
        $routeFilters = $this->routeFilters();
        $methodFilters = $this->methodFilters();

        if ($pathFilters !== []) {
            $api->filterPath($pathFilters);
        }

        if ($routeFilters !== []) {
            $api->filterRoute($routeFilters);
        }

        if ($methodFilters !== []) {
            $api->filterMethod($methodFilters);
        }
    }

    protected function docs(): array
    {
        return collect(Arr::wrap(config('inspec.docs', [])))
            ->filter(fn (mixed $documentation) => is_string($documentation) && trim($documentation) !== '')
            ->map(fn (string $documentation) => trim($documentation))
            ->values()
            ->all();
    }

    protected function pathFilters(): array
    {
        return collect(Arr::wrap($this->option('path')))
            ->filter(fn (mixed $path) => is_string($path) && trim($path) !== '')
            ->map(fn (string $path) => trim($path))
            ->values()
            ->all();
    }

    protected function routeFilters(): array
    {
        return collect(Arr::wrap($this->option('route')))
            ->filter(fn (mixed $route) => is_string($route) && trim($route) !== '')
            ->map(fn (string $route) => trim($route))
            ->values()
            ->all();
    }

    protected function methodFilters(): array
    {
        return collect(Arr::wrap($this->option('method')))
            ->filter(fn (mixed $method) => is_string($method) && trim($method) !== '')
            ->map(fn (string $method) => trim($method))
            ->values()
            ->all();
    }

    protected function outputDirectory(): ?string
    {
        $output = config('inspec.output', 'openapi');

        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        return $this->resolveBasePath(trim($output));
    }

    protected function resolveOutputPath(string $directory, string $name): string
    {
        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name.'.yaml';
    }

    protected function resolveBasePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    protected function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
    }
}
