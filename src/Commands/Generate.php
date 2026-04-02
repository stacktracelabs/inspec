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
    protected $signature = 'inspec:generate {api?}';

    protected $description = 'Generate the configured OpenAPI spec files.';

    public function handle(): int
    {
        $docs = $this->docs();

        if ($docs === []) {
            $this->error('The [inspec.docs] configuration is empty.');

            return self::FAILURE;
        }

        $outputDirectory = $this->outputDirectory();

        if (is_null($outputDirectory)) {
            $this->error('The [inspec.output] configuration is empty.');

            return self::FAILURE;
        }

        $selectedApi = trim((string) $this->argument('api'));
        $specifications = [];

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

            if ($selectedApi !== '' && $name !== $selectedApi) {
                continue;
            }

            $output = $this->resolveOutputPath($outputDirectory, $name);

            if (array_key_exists($name, $specifications)) {
                $this->error("The API [{$name}] is defined by more than one documentation class.");

                return self::FAILURE;
            }

            try {
                $yaml = $api->toOpenAPI()->toYaml();
            } catch (Throwable $e) {
                $this->error("Unable to generate API [{$name}]: {$e->getMessage()}");

                return self::FAILURE;
            }

            $specifications[$name] = [
                'output' => $output,
                'yaml' => $yaml,
            ];
        }

        if ($selectedApi !== '' && $specifications === []) {
            $this->error("The configured API [{$selectedApi}] does not exist.");

            return self::FAILURE;
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

    protected function docs(): array
    {
        return collect(Arr::wrap(config('inspec.docs', [])))
            ->filter(fn (mixed $documentation) => is_string($documentation) && trim($documentation) !== '')
            ->map(fn (string $documentation) => trim($documentation))
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
