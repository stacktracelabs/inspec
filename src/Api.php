<?php


namespace StackTrace\Inspec;


use Closure;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use StackTrace\Inspec\Operations\Broadcasting\AuthenticateWebsocketUserOperation;
use StackTrace\Inspec\Operations\Broadcasting\AuthorizeWebsocketChannelOperation;
use StackTrace\Inspec\Paginators\CursorPaginator;
use StackTrace\Inspec\Paginators\LengthAwarePaginator;
use StackTrace\Inspec\Responses\StandardSuccessResponse;
use StackTrace\Inspec\Responses\TooManyRequestsResponse;
use StackTrace\Inspec\Responses\ValidationErrorResponse;
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

    protected bool $sanctum = true;

    protected bool $broadcasting = true;

    protected ?Closure $broadcastingCallback = null;

    protected string $prefix = '';

    protected LengthAwarePaginator $pagination;

    protected CursorPaginator $cursorPagination;

    protected SuccessResponse $successResponse;

    /**
     * @var array<int, Response|null>
     */
    protected array $errorResponses = [];

    public function __construct()
    {
        $this->title = config('app.name', 'Laravel');
        $this->servers = [];
        $this->pagination = new LengthAwarePaginator();
        $this->cursorPagination = new CursorPaginator();
        $this->successResponse = new StandardSuccessResponse();
        $this->errorResponses = [
            422 => new ValidationErrorResponse(),
            429 => new TooManyRequestsResponse(),
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

    public function withPagination(LengthAwarePaginator $pagination): static
    {
        $this->pagination = $pagination;

        return $this;
    }

    public function withCursorPagination(CursorPaginator $pagination): static
    {
        $this->cursorPagination = $pagination;

        return $this;
    }

    public function withSuccessResponse(SuccessResponse $response): static
    {
        $this->successResponse = $response;

        return $this;
    }

    public function withValidationErrorResponse(Response $response): static
    {
        return $this->withErrorResponse(422, $response);
    }

    public function withoutValidationErrorResponse(): static
    {
        return $this->withoutErrorResponse(422);
    }

    public function withTooManyRequestsResponse(Response $response): static
    {
        return $this->withErrorResponse(429, $response);
    }

    public function withoutTooManyRequestsResponse(): static
    {
        return $this->withoutErrorResponse(429);
    }

    public function withErrorResponse(int $code, Response $response): static
    {
        $this->assertSupportedErrorResponseCode($code);
        $this->errorResponses[$code] = $response;

        return $this;
    }

    public function withoutErrorResponse(int $code): static
    {
        $this->assertSupportedErrorResponseCode($code);
        $this->errorResponses[$code] = null;

        return $this;
    }

    public function withSanctum(): static
    {
        $this->sanctum = true;

        return $this;
    }

    public function withoutSanctum(): static
    {
        $this->sanctum = false;

        return $this;
    }

    public function withBroadcasting(?callable $callback = null): static
    {
        $this->broadcasting = true;
        $this->broadcastingCallback = $callback === null ? null : Closure::fromCallable($callback);

        return $this;
    }

    public function withoutBroadcasting(): static
    {
        $this->broadcasting = false;

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
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
        ?Operation $operation = null,
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
            'operation' => $this->buildOperation(
                tags: $tags,
                summary: $summary,
                description: $description,
                route: $route,
                query: $query,
                request: $request,
                response: $response,
                paginatedResponse: $paginatedResponse,
                cursorPaginatedResponse: $cursorPaginatedResponse,
                responseCode: $responseCode,
                additionalResponses: $additionalResponses,
                deprecated: $deprecated,
                multipart: $multipart,
                operation: $operation,
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
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
        ?Operation $operation = null,
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
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            deprecated: $deprecated,
            multipart: $multipart,
            operation: $operation,
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
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
        ?Operation $operation = null,
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
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            deprecated: $deprecated,
            multipart: $multipart,
            operation: $operation,
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
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
        ?Operation $operation = null,
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
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            deprecated: $deprecated,
            multipart: $multipart,
            operation: $operation,
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
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
        ?Operation $operation = null,
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
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            deprecated: $deprecated,
            multipart: $multipart,
            operation: $operation,
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
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
        ?Operation $operation = null,
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
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            deprecated: $deprecated,
            multipart: $multipart,
            operation: $operation,
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
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
        ?Operation $operation = null,
    ): static {
        $name = trim($name);

        if ($name === '') {
            throw GeneratorException::withMessage('The route name cannot be empty.');
        }

        $this->manualRoutes[] = [
            'type' => 'name',
            'name' => $name,
            'operation' => $this->buildOperation(
                tags: $tags,
                summary: $summary,
                description: $description,
                route: $route,
                query: $query,
                request: $request,
                response: $response,
                paginatedResponse: $paginatedResponse,
                cursorPaginatedResponse: $cursorPaginatedResponse,
                responseCode: $responseCode,
                additionalResponses: $additionalResponses,
                deprecated: $deprecated,
                multipart: $multipart,
                operation: $operation,
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
        $document->withPagination($this->pagination);
        $document->withCursorPagination($this->cursorPagination);
        $document->withSuccessResponse($this->successResponse);

        foreach ($this->errorResponses as $code => $response) {
            if ($response instanceof Response) {
                $document->withErrorResponse($code, $response);
            } else {
                $document->withoutErrorResponse($code);
            }
        }

        if (! $this->sanctum) {
            $document->withoutSanctum();
        }

        $this->documentControllerRoutes($document);
        $this->documentManualRoutes($document);
        $this->documentBroadcastingRoutes($document);

        $document->info([
            'title' => $this->title,
            'description' => $this->description,
            'version' => $this->version,
        ]);

        foreach ($this->servers as $name => $url) {
            $document->server($name, $url);
        }

        if ($this->sanctum && $document->usesSanctumSecurity()) {
            $document->securitySchema('bearerAuth', 'http', 'bearer');
        }

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
                $routes = $this->routesForAction($action);

                if ($routes->isEmpty()) {
                    continue;
                }

                foreach ($routes as $route) {
                    if (! $this->matchesRouteFilters($route)) {
                        continue;
                    }

                    $operation = Operation::fromRoute($attribute->newInstance());

                    foreach ($this->documentedMethodsForRoute($route) as $httpMethod) {
                        $document->route($route, $operation, $httpMethod, $this->resolveDocumentPath($route->uri()));
                    }
                }
            }
        });
    }

    protected function routesForAction(string $action): Collection
    {
        return collect(Route::getRoutes()->get())
            ->filter(fn (LaravelRoute $route) => $this->routeAction($route) === $action)
            ->values();
    }

    protected function documentManualRoutes(OpenAPIDocument $document): void
    {
        foreach ($this->manualRoutes as $manualRoute) {
            $operation = $manualRoute['operation'];

            if ($manualRoute['type'] === 'name') {
                $route = $this->resolveRouteByName($manualRoute['name']);

                if (! $this->matchesRouteFilters($route)) {
                    continue;
                }

                foreach ($this->documentedMethodsForRoute($route) as $method) {
                    $document->route($route, $operation, $method, $this->resolveDocumentPath($route->uri()));
                }

                continue;
            }

            $route = $this->resolveRouteByMethodAndUri($manualRoute['method'], $manualRoute['uri']);

            if (! $this->shouldDocumentRouteMethod($route, $manualRoute['method'])) {
                continue;
            }

            $document->route($route, $operation, $manualRoute['method'], $this->resolveDocumentPath($route->uri()));
        }
    }

    protected function documentBroadcastingRoutes(OpenAPIDocument $document): void
    {
        if (! $this->broadcasting) {
            return;
        }

        foreach ($this->broadcastingRouteDefinitions() as $definition) {
            $routes = collect(Route::getRoutes()->get())
                ->filter(fn (LaravelRoute $route) => $this->routeAction($route) === $definition['action'])
                ->values();

            foreach ($routes as $route) {
                if (! $this->matchesRouteFilters($route)) {
                    continue;
                }

                $operation = $this->resolveBroadcastingOperation($definition['operation'], $route);

                if ($operation === null) {
                    continue;
                }

                foreach ($this->documentedMethodsForRoute($route) as $method) {
                    $document->route(
                        route: $route,
                        operation: $operation,
                        method: $method,
                        path: $this->resolveDocumentPath($route->uri()),
                    );
                }
            }
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

    protected function routeAction(LaravelRoute $route): string
    {
        return ltrim($route->getActionName(), '\\');
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

    protected function broadcastingRouteDefinitions(): array
    {
        return [
            [
                'action' => BroadcastController::class.'@authenticate',
                'operation' => new AuthorizeWebsocketChannelOperation(),
            ],
            [
                'action' => BroadcastController::class.'@authenticateUser',
                'operation' => new AuthenticateWebsocketUserOperation(),
            ],
        ];
    }

    protected function resolveBroadcastingOperation(Operation $operation, LaravelRoute $route): ?Operation
    {
        $operation = clone $operation;

        if ($this->broadcastingCallback === null) {
            return $operation;
        }

        $operation = ($this->broadcastingCallback)($operation, $route);

        if ($operation === null) {
            return null;
        }

        if (! ($operation instanceof Operation)) {
            throw GeneratorException::withMessage('The broadcasting callback must return an Operation instance or null.');
        }

        return $operation;
    }

    protected function buildOperation(
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
        ?Operation $operation = null,
    ): Operation {
        $this->assertOperationArgumentsAreExclusive(
            tags: $tags,
            summary: $summary,
            description: $description,
            route: $route,
            query: $query,
            request: $request,
            response: $response,
            paginatedResponse: $paginatedResponse,
            cursorPaginatedResponse: $cursorPaginatedResponse,
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            deprecated: $deprecated,
            multipart: $multipart,
            operation: $operation,
        );

        if ($operation instanceof Operation) {
            return clone $operation;
        }

        return new Operation(
            tags: $tags,
            summary: $summary,
            description: $description,
            route: $route,
            query: $query,
            request: $request,
            response: $response,
            paginatedResponse: $paginatedResponse,
            cursorPaginatedResponse: $cursorPaginatedResponse,
            responseCode: $responseCode,
            additionalResponses: $additionalResponses,
            deprecated: $deprecated,
            multipart: $multipart,
        );
    }

    protected function assertOperationArgumentsAreExclusive(
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
        ?Operation $operation = null,
    ): void {
        if (! $operation) {
            return;
        }

        if (
            $tags !== []
            || $summary !== ''
            || $description !== ''
            || $route !== []
            || $query !== []
            || $request !== null
            || $response !== null
            || $paginatedResponse !== null
            || $cursorPaginatedResponse !== null
            || $responseCode !== 200
            || $additionalResponses !== []
            || $deprecated !== false
            || $multipart !== false
        ) {
            throw GeneratorException::withMessage('The [operation] argument cannot be combined with other operation metadata arguments.');
        }
    }

    protected function assertSupportedErrorResponseCode(int $code): void
    {
        if (! in_array($code, [422, 429], true)) {
            throw GeneratorException::withMessage("The error response [{$code}] is not supported. Supported error responses are [422, 429].");
        }
    }
}
