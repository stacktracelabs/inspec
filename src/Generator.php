<?php


namespace StackTrace\Inspec;


use StackTrace\Inspec\Route as RouteAttribute;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Generator
{
    /**
     * List of URLs to generate documentation for.
     *
     * Useful for debugging and developing.
     */
    protected array $filter = [];

    public function __construct(
        protected string $title,
        protected string $description,
        protected string $version,
        protected array $servers,
        protected array $paths = [],
    ) { }

    /**
     * Filter the URLs for which will be documentation generated.
     */
    public function filter(string|array $urls): static
    {
        $this->filter = array_merge($this->filter, Arr::wrap($urls));

        return $this;
    }

    /**
     * Determine if the route should be filtered out.
     */
    protected function shouldFilter(\Illuminate\Routing\Route $route): bool
    {
        if (! in_array($route->uri, $this->filter)) {
            return true;
        }

        return false;
    }

    /**
     * Generate the OpenAPI documentation from source code.
     */
    public function generate(): Document
    {
        $paths = $this->paths();

        if ($paths->isEmpty()) {
            throw GeneratorException::withMessage("The controller paths are not defined.");
        }

        $document = new Document(options: [
            'prefix' => 'api',
        ]);

        collect(Finder::create()->name('*.php')->in($paths->all()))->each(function (SplFileInfo $file) use ($document) {
            $className = Str::match('/namespace\s+(.*)\s*;/', $file->getContents()).'\\'.$file->getFilenameWithoutExtension();

            if (! class_exists($className)) {
                throw GeneratorException::withMessage("Unable to determine FQCN for file {$file->getPath()}");
            }

            $clazz = new \ReflectionClass($className);

            foreach ($clazz->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attribute = collect($method->getAttributes(RouteAttribute::class))->first();

                if ($attribute) {
                    $action = $method->name == '__invoke' ? $method->class : $method->class.'@'.$method->name;

                    $route = Route::getRoutes()->getByAction($action);

                    if ($route) {
                        if (!empty($this->filter) && $this->shouldFilter($route)) {
                            continue;
                        }

                        $document->route($route, $attribute->newInstance());
                    }
                }
            }
        });

        $document->schema('Error', [
            'type' => 'object',
            'example' => [
                'message' => 'The phone number has already been taken.',
                'errors' => [
                    'phone_number' => [
                        'The phone number has already been taken.',
                    ]
                ]
            ],
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'General error message',
                ],
                'errors' => [
                    'type' => 'object',
                ]
            ],
            'required' => [
                'message',
                'errors',
            ],
        ]);

        $document->response('ErrorResponse', [
            'description' => 'Error response',
            'content' => [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/Error',
                    ]
                ],
            ]
        ]);

        $broadcastingRoute = collect(Route::getRoutes()->get())->firstWhere(fn (\Illuminate\Routing\Route $route) => $route->uri == 'api/broadcasting/auth');
        if ($broadcastingRoute && (empty($this->filter) || !$this->shouldFilter($broadcastingRoute))) {
            $document->route(
                route: $broadcastingRoute,
                description: new RouteAttribute(
                    tags: 'Broadcasting',
                    summary: 'Authorize Websocket channel',
                    request: [
                        'socket_id:string' => 'The socket identifier',
                        'channel_name:string' => 'The channel name',
                    ],
                    response: [
                        'auth:string' => 'Auth token',
                        'channel_data:string' => 'Double-encoded JSON containing channel information.'
                    ]
                )
            );
        }

        $document->info([
            'title' => $this->title,
            'description' => $this->description,
            'version' => $this->version,
        ]);

        foreach ($this->servers as $name => $url) {
            $document->server($name, $url);
        }

        $document->securitySchema('bearerAuth', 'http', 'bearer');

        return $document;
    }

    /**
     * The paths where to search for annotated controllers.
     */
    protected function paths(): Collection
    {
        return collect($this->paths);
    }
}
