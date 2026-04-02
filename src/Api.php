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
     * Regex patterns matched against generated OpenAPI paths.
     */
    protected array $pathFilters = [];

    /**
     * Laravel route names to document.
     */
    protected array $routeFilters = [];

    /**
     * HTTP methods to document.
     */
    protected array $methodFilters = [];

    protected string $prefix = '';

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

    public function prefix(string $prefix): static
    {
        $this->prefix = $this->normalizePrefix($prefix);

        return $this;
    }

    public function filter(string|array $patterns): static
    {
        return $this->filterPath($patterns);
    }

    public function filterPath(string|array $patterns): static
    {
        $patterns = collect(Arr::wrap($patterns))
            ->filter(fn (mixed $pattern) => is_string($pattern) && trim($pattern) !== '')
            ->map(fn (string $pattern) => trim($pattern))
            ->values()
            ->all();

        foreach ($patterns as $pattern) {
            $this->assertValidPathPattern($pattern);
        }

        $this->pathFilters = array_values(array_unique([
            ...$this->pathFilters,
            ...$patterns,
        ]));
 
        return $this;
    }

    public function filterRoute(string|array $routes): static
    {
        $routes = collect(Arr::wrap($routes))
            ->filter(fn (mixed $route) => is_string($route) && trim($route) !== '')
            ->map(fn (string $route) => trim($route))
            ->values()
            ->all();

        $this->routeFilters = array_values(array_unique([
            ...$this->routeFilters,
            ...$routes,
        ]));

        return $this;
    }

    public function filterMethod(string|array $methods): static
    {
        $methods = collect(Arr::wrap($methods))
            ->filter(fn (mixed $method) => is_string($method) && trim($method) !== '')
            ->map(fn (string $method) => Str::upper(trim($method)))
            ->values()
            ->all();

        $this->methodFilters = array_values(array_unique([
            ...$this->methodFilters,
            ...$methods,
        ]));

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

    public function toOpenAPI(): OpenAPIDocument
    {
        if ($this->controllerPaths === [] && $this->manualRoutes === []) {
            throw GeneratorException::withMessage('The API does not define any controller paths or documented routes.');
        }

        $this->assertControllerPathsExist();

        $document = new OpenAPIDocument();

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
        if ($broadcastingRoute && $this->matchesRouteFilters($broadcastingRoute)) {
            $methods = $this->documentedMethodsForRoute($broadcastingRoute);

            foreach ($methods as $method) {
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
                    method: $method,
                    path: $this->resolveDocumentPath($broadcastingRoute->uri()),
                );
            }
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

                if (! $this->matchesRouteFilters($route)) {
                    continue;
                }

                $description = $attribute->newInstance();

                foreach ($this->documentedMethodsForRoute($route) as $method) {
                    $document->route($route, $description, $method, $this->resolveDocumentPath($route->uri()));
                }
            }
        });
    }

    protected function documentManualRoutes(OpenAPIDocument $document): void
    {
        foreach ($this->manualRoutes as $manualRoute) {
            $description = $manualRoute['description'];

            if ($manualRoute['type'] === 'name') {
                $route = $this->resolveRouteByName($manualRoute['name']);

                if (! $this->matchesRouteFilters($route)) {
                    continue;
                }

                foreach ($this->documentedMethodsForRoute($route) as $method) {
                    $document->route($route, $description, $method, $this->resolveDocumentPath($route->uri()));
                }

                continue;
            }

            $route = $this->resolveRouteByMethodAndUri($manualRoute['method'], $manualRoute['uri']);

            if (! $this->shouldDocumentRouteMethod($route, $manualRoute['method'])) {
                continue;
            }

            $document->route($route, $description, $manualRoute['method'], $this->resolveDocumentPath($route->uri()));
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

    protected function matchesRouteFilters(LaravelRoute $route): bool
    {
        if ($this->pathFilters !== []) {
            $path = $this->resolveDocumentPath($route->uri());

            $matchesPath = collect($this->pathFilters)->contains(
                fn (string $pattern) => @preg_match($this->compilePathPattern($pattern), $path) === 1
            );

            if (! $matchesPath) {
                return false;
            }
        }

        if ($this->routeFilters !== []) {
            $name = $route->getName();

            if (! is_string($name) || ! in_array($name, $this->routeFilters, true)) {
                return false;
            }
        }

        return true;
    }

    protected function documentedMethodsForRoute(LaravelRoute $route): array
    {
        return collect($route->methods())
            ->map(fn (string $method) => Str::upper($method))
            ->reject(fn (string $method) => $method === 'HEAD' || $method === 'OPTIONS')
            ->filter(fn (string $method) => $this->matchesMethodFilters($method))
            ->values()
            ->all();
    }

    protected function shouldDocumentRouteMethod(LaravelRoute $route, string $method): bool
    {
        return $this->matchesRouteFilters($route)
            && $this->matchesMethodFilters($method);
    }

    protected function matchesMethodFilters(string $method): bool
    {
        $method = Str::upper($method);

        if ($method === 'HEAD' || $method === 'OPTIONS') {
            return false;
        }

        if ($this->methodFilters === []) {
            return true;
        }

        return in_array($method, $this->methodFilters, true);
    }

    protected function resolveDocumentPath(string $uri): string
    {
        $uri = $this->normalizeLookupUri($uri);

        if ($uri === '') {
            return '/';
        }

        if ($this->prefix !== '') {
            if ($uri === $this->prefix) {
                return '/';
            }

            if (Str::startsWith($uri, $this->prefix.'/')) {
                $uri = Str::after($uri, $this->prefix.'/');
            }
        }

        return '/'.$uri;
    }

    protected function normalizePrefix(string $prefix): string
    {
        return $this->normalizeLookupUri($prefix);
    }

    protected function assertValidPathPattern(string $pattern): void
    {
        if (@preg_match($this->compilePathPattern($pattern), '') === false) {
            throw GeneratorException::withMessage("The path filter [{$pattern}] is not a valid regex.");
        }
    }

    protected function compilePathPattern(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~u';
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
