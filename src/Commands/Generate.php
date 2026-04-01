<?php


namespace StackTrace\Inspec\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use StackTrace\Inspec\Generator;
use Throwable;

class Generate extends Command
{
    protected $signature = 'inspec:generate {output?}';

    protected $description = 'Generate the OpenAPI spec.';

    public function handle(): int
    {
        $paths = $this->paths();

        if ($paths === []) {
            $this->error('The [inspec.paths] configuration is empty.');

            return self::FAILURE;
        }

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                $this->error("The configured controller path [{$path}] does not exist or is not a directory.");

                return self::FAILURE;
            }
        }

        $output = $this->resolveOutputPath($this->argument('output') ?: config('inspec.output'));

        if (is_null($output)) {
            $this->error('The output path is not configured.');

            return self::FAILURE;
        }

        try {
            $yaml = new Generator(
                title: config('inspec.title', config('app.name', 'Laravel')),
                description: config('inspec.description', ''),
                version: config('inspec.version', '1.0.0'),
                servers: $this->servers(),
                paths: $paths,
            )->generate()->toYaml();

            File::ensureDirectoryExists(dirname($output));
            File::put($output, $yaml);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("OpenAPI spec written to [{$output}].");

        return self::SUCCESS;
    }

    protected function paths(): array
    {
        return collect(Arr::wrap(config('inspec.paths', [])))
            ->filter(fn (mixed $path) => is_string($path) && trim($path) !== '')
            ->values()
            ->all();
    }

    protected function servers(): array
    {
        return collect(Arr::wrap(config('inspec.servers', [])))
            ->filter(fn (mixed $url, mixed $description) => is_string($description) && trim((string) $description) !== '' && is_string($url) && trim($url) !== '')
            ->map(fn (string $url) => trim($url))
            ->all();
    }

    protected function resolveOutputPath(mixed $output): ?string
    {
        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        $output = trim($output);

        if ($this->isAbsolutePath($output)) {
            return $output;
        }

        return base_path($output);
    }

    protected function isAbsolutePath(string $path): bool
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2}|\/)/', $path) === 1;
    }
}
