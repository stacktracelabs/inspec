<?php


namespace StackTrace\Inspec;


use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use StackTrace\Inspec\Route as RouteAttribute;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Api
{
    protected ?string $name = null;

    protected string $title;

    protected string $description = '';

    protected string $version = '1.0.0';

    protected array $servers = [];

    protected array $controllerPaths = [];

    protected array $manualRoutes = [];

    /**
     * List of URLs to generate documentation for.
     *
     * Useful for debugging and developing.
     */
    protected array $filter = [];

    public function __construct()
    {
        $this->title = config('app.name', 'Laravel');
        $this->servers = [
            'Local' => config('app.url', 'http://localhost'),
        ];
    }

    public function name(string $name): static
    {
        $name = trim($name);

        if ($name === '') {
            throw GeneratorException::withMessage('The API name cannot be empty.');
        }

        if (str_contains($name, '/') || str_contains($name, '\\')) {
            throw GeneratorException::withMessage('The API name cannot contain directory separators.');
        }

        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function title(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = trim($description);

        return $this;
    }

    public function version(string $version): static
    {
        $this->version = trim($version);

        return $this;
    }

    public function server(string $description, string $url): static
    {
        $description = trim($description);
        $url = trim($url);

        if ($description === '' || $url === '') {
            return $this;
        }

        $this->servers[$description] = $url;

        return $this;
    }

    public function servers(array $servers): static
    {
        $this->servers = collect($servers)
            ->filter(fn (mixed $url, mixed $description) => is_string($description) && trim($description) !== '' && is_string($url) && trim($url) !== '')
            ->mapWithKeys(fn (string $url, string $description) => [trim($description) => trim($url)])
            ->all();

        return $this;
    }

    public function controllers(string|array $paths): static
    {
        $this->controllerPaths = array_values(array_unique([
            ...$this->controllerPaths,
            ...collect(Arr::wrap($paths))
                ->filter(fn (mixed $path) => is_string($path) && trim($path) !== '')
                ->map(fn (string $path) => trim($path))
                ->all(),
        ]));

        return $this;
    }

    /**
     * Filter the URLs for which will be documentation generated.
     */
    public function filter(string|array $urls): static
    {
        $this->filter = array_merge($this->filter, Arr::wrap($urls));

        return $this;
    }

    public function method(
        string $method,
        string $uri,
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        ?array $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        ?array $paginatedMeta = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        ?int $defaultPerPage = null,
        bool $deprecated = false,
        bool $multipart = false,
    ): static {
        $method = Str::upper(trim($method));
        $uri = $this->normalizeLookupUri($uri);

        if ($method === '') {
            throw GeneratorException::withMessage('The route method cannot be empty.');
        }

        $this->manualRoutes[] = [
            'type' => 'method',
            'method' => $method,
            'uri' => $uri,
            'description' => $this->buildRouteAttribute(
                tags: $tags,
                summary: $summary,
                description: $description,
                route: $route,
                query: $query,
                request: $request,
                response: $response,
                paginatedResponse: $paginatedResponse,
                cursorPaginatedResponse: $cursorPaginatedResponse,
                paginatedMeta: $paginatedMeta,
                responseCode: $responseCode,
                additionalResponses: $additionalResponses,
                defaultPerPage: $defaultPerPage,
                deprecated: $deprecated,
                multipart: $multipart,
            ),
        ];

        return $this;
    }

    public function get(
        string $uri,
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        ?array $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        ?array $paginatedMeta = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        ?int $defaultPerPage = null,
        bool $deprecated = false,
        bool $multipart = false,
    ): static {
        return $this->method(
            method: 'GET',
            uri: $uri,
            tags: $tags,
            summary: $summary,
            description: $description,
            route: $route,
            query: $query,
            request: $request,
            response: $response,
            paginatedResponse: $paginatedResponse,
            cursorPaginatedResponse: $cursorPaginatedResponse,
            paginatedMeta: $paginatedMeta,
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            defaultPerPage: $defaultPerPage,
            deprecated: $deprecated,
            multipart: $multipart,
        );
    }

    public function post(
        string $uri,
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        ?array $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        ?array $paginatedMeta = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        ?int $defaultPerPage = null,
        bool $deprecated = false,
        bool $multipart = false,
    ): static {
        return $this->method(
            method: 'POST',
            uri: $uri,
            tags: $tags,
            summary: $summary,
            description: $description,
            route: $route,
            query: $query,
            request: $request,
            response: $response,
            paginatedResponse: $paginatedResponse,
            cursorPaginatedResponse: $cursorPaginatedResponse,
            paginatedMeta: $paginatedMeta,
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            defaultPerPage: $defaultPerPage,
            deprecated: $deprecated,
            multipart: $multipart,
        );
    }

    public function put(
        string $uri,
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        ?array $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        ?array $paginatedMeta = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        ?int $defaultPerPage = null,
        bool $deprecated = false,
        bool $multipart = false,
    ): static {
        return $this->method(
            method: 'PUT',
            uri: $uri,
            tags: $tags,
            summary: $summary,
            description: $description,
            route: $route,
            query: $query,
            request: $request,
            response: $response,
            paginatedResponse: $paginatedResponse,
            cursorPaginatedResponse: $cursorPaginatedResponse,
            paginatedMeta: $paginatedMeta,
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            defaultPerPage: $defaultPerPage,
            deprecated: $deprecated,
            multipart: $multipart,
        );
    }

    public function patch(
        string $uri,
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        ?array $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        ?array $paginatedMeta = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        ?int $defaultPerPage = null,
        bool $deprecated = false,
        bool $multipart = false,
    ): static {
        return $this->method(
            method: 'PATCH',
            uri: $uri,
            tags: $tags,
            summary: $summary,
            description: $description,
            route: $route,
            query: $query,
            request: $request,
            response: $response,
            paginatedResponse: $paginatedResponse,
            cursorPaginatedResponse: $cursorPaginatedResponse,
            paginatedMeta: $paginatedMeta,
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            defaultPerPage: $defaultPerPage,
            deprecated: $deprecated,
            multipart: $multipart,
        );
    }

    public function delete(
        string $uri,
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        ?array $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        ?array $paginatedMeta = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        ?int $defaultPerPage = null,
        bool $deprecated = false,
        bool $multipart = false,
    ): static {
        return $this->method(
            method: 'DELETE',
            uri: $uri,
            tags: $tags,
            summary: $summary,
            description: $description,
            route: $route,
            query: $query,
            request: $request,
            response: $response,
            paginatedResponse: $paginatedResponse,
            cursorPaginatedResponse: $cursorPaginatedResponse,
            paginatedMeta: $paginatedMeta,
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            defaultPerPage: $defaultPerPage,
            deprecated: $deprecated,
            multipart: $multipart,
        );
    }

    public function route(
        string $name,
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        ?array $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        ?array $paginatedMeta = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        ?int $defaultPerPage = null,
        bool $deprecated = false,
        bool $multipart = false,
    ): static {
        $name = trim($name);

        if ($name === '') {
            throw GeneratorException::withMessage('The route name cannot be empty.');
        }

        $this->manualRoutes[] = [
            'type' => 'name',
            'name' => $name,
            'description' => $this->buildRouteAttribute(
                tags: $tags,
                summary: $summary,
                description: $description,
                route: $route,
                query: $query,
                request: $request,
                response: $response,
                paginatedResponse: $paginatedResponse,
                cursorPaginatedResponse: $cursorPaginatedResponse,
                paginatedMeta: $paginatedMeta,
                responseCode: $responseCode,
                additionalResponses: $additionalResponses,
                defaultPerPage: $defaultPerPage,
                deprecated: $deprecated,
                multipart: $multipart,
            ),
        ];

        return $this;
    }

    /**
     * Determine if the route should be filtered out.
     */
    protected function shouldFilter(LaravelRoute $route): bool
    {
        if (! in_array($route->uri(), $this->filter, true)) {
            return true;
        }

        return false;
    }

    public function toOpenAPI(): OpenAPIDocument
    {
        if ($this->controllerPaths === [] && $this->manualRoutes === []) {
            throw GeneratorException::withMessage('The API does not define any controller paths or documented routes.');
        }

        $this->assertControllerPathsExist();

        $document = new OpenAPIDocument(options: [
            'prefix' => 'api',
        ]);

        $this->documentControllerRoutes($document);
        $this->documentManualRoutes($document);

        $document->schema('Error', [
            'type' => 'object',
            'example' => [
                'message' => 'The phone number has already been taken.',
                'errors' => [
                    'phone_number' => [
                        'The phone number has already been taken.',
                    ],
                ],
            ],
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'General error message',
                ],
                'errors' => [
                    'type' => 'object',
                ],
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
                    ],
                ],
            ],
        ]);

        $broadcastingRoute = collect(Route::getRoutes()->get())->firstWhere(fn (LaravelRoute $route) => $route->uri() === 'api/broadcasting/auth');
        if ($broadcastingRoute && (empty($this->filter) || ! $this->shouldFilter($broadcastingRoute))) {
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
                        'channel_data:string' => 'Double-encoded JSON containing channel information.',
                    ],
                ),
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

    protected function controllerPaths(): Collection
    {
        return collect($this->controllerPaths);
    }

    protected function assertControllerPathsExist(): void
    {
        foreach ($this->controllerPaths() as $path) {
            if (! is_dir($path)) {
                throw GeneratorException::withMessage("The configured controller path [{$path}] does not exist or is not a directory.");
            }
        }
    }

    protected function documentControllerRoutes(OpenAPIDocument $document): void
    {
        $paths = $this->controllerPaths();

        if ($paths->isEmpty()) {
            return;
        }

        collect(Finder::create()->name('*.php')->in($paths->all()))->each(function (SplFileInfo $file) use ($document) {
            $className = Str::match('/namespace\s+(.*)\s*;/', $file->getContents()).'\\'.$file->getFilenameWithoutExtension();

            if (! class_exists($className)) {
                throw GeneratorException::withMessage("Unable to determine FQCN for file {$file->getPath()}");
            }

            $clazz = new \ReflectionClass($className);

            foreach ($clazz->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $attribute = collect($method->getAttributes(RouteAttribute::class))->first();

                if (! $attribute) {
                    continue;
                }

                $action = $method->name === '__invoke' ? $method->class : $method->class.'@'.$method->name;
                $route = Route::getRoutes()->getByAction($action);

                if (! $route) {
                    continue;
                }

                if (! empty($this->filter) && $this->shouldFilter($route)) {
                    continue;
                }

                $document->route($route, $attribute->newInstance());
            }
        });
    }

    protected function documentManualRoutes(OpenAPIDocument $document): void
    {
        foreach ($this->manualRoutes as $manualRoute) {
            $description = $manualRoute['description'];

            if ($manualRoute['type'] === 'name') {
                $route = $this->resolveRouteByName($manualRoute['name']);

                if (! empty($this->filter) && $this->shouldFilter($route)) {
                    continue;
                }

                $document->route($route, $description);

                continue;
            }

            $route = $this->resolveRouteByMethodAndUri($manualRoute['method'], $manualRoute['uri']);

            if (! empty($this->filter) && $this->shouldFilter($route)) {
                continue;
            }

            $document->route($route, $description, $manualRoute['method']);
        }
    }

    protected function resolveRouteByName(string $name): LaravelRoute
    {
        $route = Route::getRoutes()->getByName($name);

        if (! $route) {
            throw GeneratorException::withMessage("Unable to resolve route [{$name}] by name.");
        }

        return $route;
    }

    protected function resolveRouteByMethodAndUri(string $method, string $uri): LaravelRoute
    {
        $matches = collect(Route::getRoutes()->get())
            ->filter(fn (LaravelRoute $route) => $this->matchesMethodAndUri($route, $method, $uri))
            ->values();

        $displayUri = '/'.$uri;

        if ($matches->isEmpty()) {
            throw GeneratorException::withMessage("Unable to resolve route [{$method} {$displayUri}].");
        }

        if ($matches->count() > 1) {
            throw GeneratorException::withMessage("The route [{$method} {$displayUri}] is ambiguous.");
        }

        return $matches->first();
    }

    protected function matchesMethodAndUri(LaravelRoute $route, string $method, string $uri): bool
    {
        return in_array($method, $route->methods(), true)
            && $this->normalizeLookupUri($route->uri()) === $uri;
    }

    protected function normalizeLookupUri(string $uri): string
    {
        return trim(trim($uri), '/');
    }

    protected function buildRouteAttribute(
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        ?array $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        ?array $paginatedMeta = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        ?int $defaultPerPage = null,
        bool $deprecated = false,
        bool $multipart = false,
    ): RouteAttribute {
        return new RouteAttribute(
            tags: $tags,
            summary: $summary,
            description: $description,
            route: $route,
            query: $query,
            request: $request,
            response: $response,
            paginatedResponse: $paginatedResponse,
            cursorPaginatedResponse: $cursorPaginatedResponse,
            paginatedMeta: $paginatedMeta,
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            defaultPerPage: $defaultPerPage,
            deprecated: $deprecated,
            multipart: $multipart,
        );
    }
}
