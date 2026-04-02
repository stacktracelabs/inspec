<?php


namespace StackTrace\Inspec;


use StackTrace\Inspec\Route as RouteAttribute;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;
use StackTrace\Inspec\Paginators\CursorPaginator;
use StackTrace\Inspec\Paginators\LengthAwarePaginator;
use StackTrace\Inspec\Responses\TooManyRequestsResponse;
use StackTrace\Inspec\Responses\ValidationErrorResponse;
use Symfony\Component\Yaml\Yaml;

class OpenAPIDocument
{
    protected array $info = [];

    protected array $servers = [];

    protected array $responses = [];

    protected array $tags = [];

    protected array $paths = [];

    protected array $securitySchemas = [];

    protected array $schemas = [];

    protected string $version = '3.0.0';

    protected array $schemaStack = [];

    protected bool $sanctum = true;

    protected bool $usesSanctumSecurity = false;

    protected LengthAwarePaginator $pagination;

    protected CursorPaginator $cursorPagination;

    /**
     * @var array<int, Response|null>
     */
    protected array $errorResponses = [];

    public function __construct()
    {
        $this->pagination = new LengthAwarePaginator();
        $this->cursorPagination = new CursorPaginator();
        $this->errorResponses = [
            422 => new ValidationErrorResponse(),
            429 => new TooManyRequestsResponse(),
        ];
    }

    public function securitySchema(string $name, string $type, string $scheme): static
    {
        Arr::set($this->securitySchemas, $name, [
            'type' => $type,
            'scheme' => $scheme,
        ]);

        return $this;
    }

    public function response(string $name, array $definition): static
    {
        $this->responses[$name] = $definition;

        return $this;
    }

    public function server(string $description, string $url): static
    {
        $this->servers[] = [
            'url' => $url,
            'description' => $description,
        ];

        return $this;
    }

    public function info(array $info): static
    {
        $this->info = $info;

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
        $this->usesSanctumSecurity = false;

        return $this;
    }

    public function withPagination(LengthAwarePaginator $pagination): static
    {
        $this->pagination = $pagination;

        return $this;
    }

    public function withCursorPagination(CursorPaginator $cursorPagination): static
    {
        $this->cursorPagination = $cursorPagination;

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

    public function usesSanctumSecurity(): bool
    {
        return $this->usesSanctumSecurity;
    }

    public function tag(string $name): static
    {
        $this->tags[$name] = ['name' => $name];

        return $this;
    }

    public function schema(string $name, array $definition): static
    {
        Arr::set($this->schemas, $name, $definition);

        return $this;
    }

    protected function resolveSchemaPathFromSchemaObject(mixed $object): string
    {
        if (is_string($object)) {
            $object = new $object;
        }

        if (! ($object instanceof SchemaObject)) {
            throw GeneratorException::withMessage("Not a schema object.");
        }

        $definition = $this->buildObject($object->attributes, true);

        $this->schema($object->name, $definition);

        return "#/components/schemas/{$object->name}";
    }

    protected function resolveSchemaPathFromTransformer(string $class): string
    {
        $schema = $this->gatherObjectSchemaFromClass($class);

        if (is_null($schema)) {
            throw GeneratorException::withMessage("The [$class] does not have any Schema definition.");
        }

        [$name, $definition] = $schema;

        $this->schema($name, $definition);

        return "#/components/schemas/{$name}";
    }

    /**
     * Retrieve schema name from given Transformer class.
     */
    protected function resolveSchemaNameFromTransformerClass(string $class): string
    {
        $clazz = new \ReflectionClass($class);

        $method = $clazz->getMethod('transform');

        /** @var \StackTrace\Inspec\Schema $attribute */
        $attribute = Arr::first($method->getAttributes(Schema::class))->newInstance();

        return $attribute->name ?: Str::replaceLast('Transformer', '', class_basename($clazz->name));
    }

    public function gatherObjectSchemaFromClass(string $class): ?array
    {
        if ($this->isTransformer($class)) {
            $clazz = new \ReflectionClass($class);

            $method = $clazz->getMethod('transform');

            /** @var \StackTrace\Inspec\Schema $attribute */
            $attribute = Arr::first($method->getAttributes(Schema::class))->newInstance();

            $name = $this->resolveSchemaNameFromTransformerClass($class);

            Arr::set($this->schemaStack, $name, true);

            $schema = $this->buildObject($attribute->object, schemaObjectFlag: true);

            // Check expanded properties.
            foreach ($clazz->getMethods() as $includeMethod) {
                $expandAttribute = Arr::first($includeMethod->getAttributes(ExpandItem::class)) ?: Arr::first($includeMethod->getAttributes(ExpandCollection::class));

                if ($expandAttribute && Str::startsWith($includeMethod->name, 'include')) {
                    $attrInstance = $expandAttribute->newInstance();
                    $field = Str::snake(Str::replaceFirst('include', '', $includeMethod->name));

                    $resolveExpandedSchema = function (string $transformer) {
                        if (! class_exists($transformer)) {
                            GeneratorException::withMessage("The transformer [$transformer] does not exist.");
                        }

                        $expandName = $this->resolveSchemaNameFromTransformerClass($transformer);

                        // This will prevent recursive generation of the schemas.
                        if (Arr::has($this->schemaStack, $expandName)) {
                            return $expandName;
                        }

                        [$expandName, $expandSchema] = $this->gatherObjectSchemaFromClass($transformer);

                        $this->schema($expandName, $expandSchema);

                        return $expandName;
                    };

                    $expandedValue = null;

                    if ($attrInstance instanceof ExpandCollection) {
                        $expandName = $resolveExpandedSchema($attrInstance->transformer);

                        $expandedValue = [
                            'type' => 'object',
                            'properties' => [
                                'data' => $this->asArray([
                                    '$ref' => "#/components/schemas/{$expandName}",
                                ])
                            ]
                        ];
                    } else if ($attrInstance instanceof ExpandItem) {
                        if (is_array($attrInstance->transformer)) {
                            $refs = collect($attrInstance->transformer)
                                ->map(fn ($it) => $resolveExpandedSchema($it))
                                ->map(fn ($it) => ['$ref' => "#/components/schemas/{$it}"])
                                ->all();

                            $expandedValue = [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'allOf' => $refs,
                                    ]
                                ]
                            ];
                        } else {
                            $expandName = $resolveExpandedSchema($attrInstance->transformer);

                            $expandedValue = [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        '$ref' => "#/components/schemas/{$expandName}"
                                    ]
                                ]
                            ];
                        }
                    }

                    if ($expandedValue) {
                        Arr::set($schema, "properties.{$field}", $expandedValue);
                    }
                }
            }

            Arr::forget($this->schemaStack, $name);

            return [$name, $schema];
        }

        return null;
    }

    protected function isTransformer(string $class): bool
    {
        return in_array(TransformerAbstract::class, class_parents($class));
    }

    protected function isSchemaObject(mixed $candidate): bool
    {
        if (is_string($candidate)) {
            return class_exists($candidate) && in_array(SchemaObject::class, class_parents($candidate));
        }

        return $candidate instanceof SchemaObject;
    }

    protected function asArray(array $def): array
    {
        return [
            'type' => 'array',
            'items' => $def,
        ];
    }

    protected function buildObject(array $def, bool $schemaObjectFlag = false, bool &$fileDetected = false): array
    {
        $object = [
            'type' => 'object',
        ];

        $properties = [];

        foreach ($def as $key => $description) {
            $property = Property::compile($key);

            // Check for metadata, properties starting with @:
            if (Str::startsWith($property->name, '@')) {
                // Example value of the object.
                if ($property->name == '@example') {
                    $object['example'] = $description;
                }

                if ($property->name == '@description') {
                    $object['description'] = $description;
                }

                continue;
            }

            // One time schema object.
            if ($description instanceof SchemaObject) {
                $oneTimeSchemaObject = $this->buildObject($description->attributes, $schemaObjectFlag);
                $this->schema($description->name, $oneTimeSchemaObject);

                $ref = [
                    '$ref' => "#/components/schemas/{$description->name}"
                ];

                // If the property is array, the value is array of schemes, otherwise it is just a single scheme.
                if ($property->isArray()) {
                    $properties[$property->name] = $this->asArray($ref);
                } else {
                    $properties[$property->name] = $ref;
                }

                continue;
            }

            // If the argument is class name, the value should be a link to scheme.
            if (is_string($description) && class_exists($description)) {
                $schema = $this->gatherObjectSchemaFromClass($description);

                if (is_null($schema)) {
                    throw GeneratorException::withMessage("The schema could not be determined for class [$description]");
                }
                [$schemaName, $schemaObject] = $schema;

                $this->schema($schemaName, $schemaObject);

                $ref = [
                    '$ref' => "#/components/schemas/{$schemaName}"
                ];

                // If the property is array, the value is array of schemes, otherwise it is just a single scheme.
                if ($property->isArray()) {
                    $properties[$property->name] = $this->asArray($ref);
                } else {
                    $properties[$property->name] = $ref;
                }

                continue;
            }

            // The type is transformer.
            if (class_exists($property->type) && $this->isTransformer($property->type)) {
                $properties[$property->name] = [
                    '$ref' => $this->resolveSchemaPathFromTransformer($property->type),
                    // 'description' => $description,
                ];

                continue;
            }

            // The type is schema object
            if ($this->isSchemaObject($property->type)) {
                $properties[$property->name] = [
                    '$ref' => $this->resolveSchemaPathFromSchemaObject($property->type),
                    // 'description' => $description,
                ];

                continue;
            }

            // If the argument is array, the property is an object definition.
            if (is_array($description)) {
                if ($property->isArray()) {
                    $prop = $this->asArray($this->buildObject($description, $schemaObjectFlag));
                } else {
                    $prop = $this->buildObject($description, $schemaObjectFlag);
                }
            }

            // Otherwise it is just a primitive property.
            else {
                $prop = [
                    'type' => $property->type,
                    'description' => $description,
                ];

                // If the property is array of values.
                if ($property->isArray()) {
                    $type = $property->arrayItemType();

                    if (is_null($type)) {
                        throw GeneratorException::withMessage("Unable to determine type of the item.");
                    }

                    if (class_exists($type)) {
                        if ($this->isTransformer($type)) {
                            $ref = $this->resolveSchemaPathFromTransformer($type);
                        } else if ($this->isSchemaObject($type)) {
                            $ref = $this->resolveSchemaPathFromSchemaObject($type);
                        } else {
                            $ref = null;
                        }

                        if (is_null($ref)) {
                            throw GeneratorException::withMessage("The schema could not be determined for class [$type]");
                        }

                        $prop['items'] = ['$ref' => $ref];
                    } else {
                        $prop['items'] = ['type' => $type];
                    }

                    if ($property->isEnum()) {
                        $prop['items']['enum'] = $property->enumCases();
                    }
                }

                // Add cases if property is an enum.
                if ($property->isEnum() && !$property->isArray()) {
                    $prop['enum'] = $property->enumCases();
                }

                // Check for file property.
                if ($property->isFile()) {
                    $prop['type'] = 'string';
                    $prop['format'] = 'binary';
                    $fileDetected = true;
                }
            }

            $nullable = false;

            // In schema objects, the ! is not supported and optional fields are not supported either.
            // email => Always present, non-nullable field
            // email? => Always present, nullable field.
            if ($schemaObjectFlag) {
                if ($property->optional) {
                    $nullable = true;
                }
            } else {
                // In request objects:
                // email => must be present in request, nullable
                // email! => must be present in request, non-nullable
                // email? => optionally present, nullable
                // email?! => optionally present, non-nullable

                // By default, OpenAPI properties are optional.
                if (! $property->optional) {
                    $pro['required'] = true;
                }

                // If the value is nullable.
                if (! $property->nonNullable) {
                    $nullable = true;
                }
            }

            if ($nullable) {
                $type = $prop['type'];

                // We consider boolean type as always non-nullable.
                if (! in_array($type, ['boolean'])) {
                    $prop['nullable'] = true;
                }
            }

            $properties[$property->name] = $prop;
        }

        $object['properties'] = $properties;

        return $object;
    }

    protected function buildResponse(array $def, string $description = '', string $type = 'application/json')
    {
        $response = [];
        if (!empty($description)) {
            $response['description'] = $description;
        } else {
            $response['description'] = 'Successful response';
        }

        $response['content'] = [
            $type => [
                'schema' => $this->buildObject($def),
            ]
        ];

        return $response;
    }

    protected function resolvePaginatedItems(array|string $def): array
    {
        if (is_string($def)) {
            if (! class_exists($def)) {
                throw GeneratorException::withMessage("The class [$def] does not exist.");
            }

            [$schema, $schemaDef] = $this->gatherObjectSchemaFromClass($def);
            $this->schema($schema, $schemaDef);

            $items = [
                '$ref' => "#/components/schemas/{$schema}",
            ];
        } else {
            throw GeneratorException::withMessage("Creating items from object definition is supported yet.");
        }

        return $items;
    }

    protected function buildPaginatorResponse(array|string $def, Paginator $paginator): array
    {
        $items = $this->resolvePaginatedItems($def);
        $properties = $this->paginatorMetaProperties($paginator);

        return [
            'description' => 'Successful response',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => $items,
                            ],
                            'meta' => [
                                'type' => 'object',
                                'properties' => $properties,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function registerPaginatorSchema(Paginator $paginator): string
    {
        $definition = $this->buildObject($paginator->object);
        $registered = $this->schemas[$paginator->name] ?? null;

        if (is_array($registered) && $registered !== $definition) {
            throw GeneratorException::withMessage("The pagination schema [{$paginator->name}] is already registered with a different definition.");
        }

        $this->schema($paginator->name, $definition);

        return "#/components/schemas/{$paginator->name}";
    }

    protected function paginatorMetaProperties(Paginator $paginator): array
    {
        $properties = [
            $paginator->metaKey => [
                '$ref' => $this->registerPaginatorSchema($paginator),
            ],
        ];

        if ($paginator->meta !== []) {
            $customMeta = Arr::get($this->buildObject($paginator->meta), 'properties');

            if (is_array($customMeta) && $customMeta !== []) {
                $properties = array_merge($properties, $customMeta);
            }
        }

        return $properties;
    }

    protected function buildRequest(array $def, string $type = 'application/json'): array
    {
        $fileDetected = false;

        $object = $this->buildObject($def, fileDetected: $fileDetected);

        $type = $fileDetected ? 'multipart/form-data' : $type;

        $block = [
            'content' => [
                $type => [
                    'schema' => $object,
                ]
            ]
        ];

        if ($type == 'multipart/form-data') {
            $block['required'] = true;
        }

        return $block;
    }

    protected function buildReusableResponseDefinition(Response $response): array
    {
        $definition = [
            'description' => $response->description,
        ];

        if ($response->headers !== []) {
            $definition['headers'] = $this->buildResponseHeaders($response->headers);
        }

        if (is_array($response->body)) {
            $definition['content'] = [
                $response->contentType => [
                    'schema' => $this->buildObject($response->body),
                ],
            ];
        }

        return $definition;
    }

    protected function buildResponseHeaders(array $definition): array
    {
        $headers = [];

        foreach ($definition as $name => $description) {
            $prop = Property::compile($name);

            $header = [
                'schema' => [
                    'type' => $prop->type,
                ],
            ];

            if (is_string($description) && $description !== '') {
                $header['description'] = $description;
            }

            if ($prop->isEnum()) {
                $header['schema']['enum'] = $prop->enumCases();
            }

            $headers[$prop->name] = $header;
        }

        return $headers;
    }

    protected function registerReusableResponse(Response $response): string
    {
        $definition = $this->buildReusableResponseDefinition($response);
        $registered = $this->responses[$response->name] ?? null;

        if (is_array($registered) && $registered !== $definition) {
            throw GeneratorException::withMessage("The response component [{$response->name}] is already registered with a different definition.");
        }

        $this->response($response->name, $definition);

        return "#/components/responses/{$response->name}";
    }

    protected function addRoute(Route $route, RouteAttribute $description, string $method, ?string $path = null): static
    {
        $this->assertPaginatorOverridesAreValid($description);

        $url = $path ?: '/'.ltrim($route->uri(), '/');

        $path = ArrayBuilder::make()
            ->setUnlessEmpty('tags', $description->tags)
            ->setUnlessEmpty('summary', $description->summary);

        if ($description->deprecated) {
            $path['deprecated'] = true;
        }

        // Parameters
        $parameters = [];
        if (! empty($description->route)) {
            $parameters = [
                ...$parameters,
                ...$this->buildPathParameters($description->route),
            ];
        }

        if (! empty($description->query)) {
            $parameters = [
                ...$parameters,
                ...$this->buildQueryParameters($description->query),
            ];
        }

        // Security
        $middleware = collect($route->gatherMiddleware());
        if ($this->sanctum && $middleware->contains('auth:sanctum')) {
            $path['security'] = [
                [
                    'bearerAuth' => []
                ]
            ];
            $this->usesSanctumSecurity = true;
        }

        if ($description->cursorPaginatedResponse) {
            $parameters = [
                ...$parameters,
                ...$this->buildQueryParameters(($description->cursorPaginator ?? $this->cursorPagination)->query),
            ];
        }

        if ($description->paginatedResponse) {
            $parameters = [
                ...$parameters,
                ...$this->buildQueryParameters(($description->paginator ?? $this->pagination)->query),
            ];
        }

        $path->setUnlessEmpty('parameters', $parameters);

        // Tags
        $tags = $description->tags;
        foreach ($tags as $tag) {
            if (! in_array($tag, $this->tags)) {
                $this->tag($tag);
            }
        }

        // Request
        if (is_array($description->request) && !empty($description->request)) {
            $path['requestBody'] = $this->buildRequest($description->request, type: $description->multipart ? 'multipart/form-data' : 'application/json');
        }

        // Response
        $responses = [];

        if (is_array($description->response) && !empty($description->response)) {
            $responses[$description->responseCode] = $this->buildResponse($description->response);
        } else if ($description->paginatedResponse != null) {
            $responses[$description->responseCode] = $this->buildPaginatorResponse($description->paginatedResponse, $description->paginator ?? $this->pagination);
        } else if ($description->cursorPaginatedResponse != null) {
            $responses[$description->responseCode] = $this->buildPaginatorResponse($description->cursorPaginatedResponse, $description->cursorPaginator ?? $this->cursorPagination);
        }

        foreach ($this->buildInferredErrorResponses($description, $middleware) as $code => $response) {
            $responses[$code] = $response;
        }

        foreach ($this->buildAdditionalResponses($description->additionalResponses) as $code => $response) {
            if ($response === null) {
                unset($responses[$code]);
                continue;
            }

            $responses[$code] = $response;
        }

        $responses = $this->finalizeRouteResponses($responses);

        if ($responses !== []) {
            $path['responses'] = $responses;
        }

        $endpoint = [
            'url' => $url,
            'method' => $method,
            'def' => (array) $path,
        ];

        $this->paths[] = $endpoint;

        return $this;
    }

    protected function buildInferredErrorResponses(RouteAttribute $description, Collection $middleware): array
    {
        $responses = [];

        if (is_array($description->request) && ! empty($description->request)) {
            $response = $this->errorResponses[422] ?? null;

            if ($response instanceof Response) {
                $responses[422] = $response;
            }
        }

        if ($this->usesThrottleMiddleware($middleware)) {
            $response = $this->errorResponses[429] ?? null;

            if ($response instanceof Response) {
                $responses[429] = $response;
            }
        }

        return $responses;
    }

    protected function buildAdditionalResponses(array $definitions): array
    {
        $responses = [];

        foreach ($definitions as $code => $definition) {
            if ($definition === null) {
                $responses[$code] = null;
                continue;
            }

            if (is_string($definition) && class_exists($definition) && is_subclass_of($definition, Response::class)) {
                $definition = new $definition();
            }

            if ($definition instanceof Response) {
                $responses[$code] = $definition;
                continue;
            }

            if (is_string($definition)) {
                $responses[$code] = [
                    'description' => $definition,
                ];
                continue;
            }

            throw GeneratorException::withMessage("The additional response [{$code}] must be null, a string, a Response instance, or a Response class string.");
        }

        return $responses;
    }

    protected function finalizeRouteResponses(array $responses): array
    {
        foreach ($responses as $code => $response) {
            if (! ($response instanceof Response)) {
                continue;
            }

            $responses[$code] = [
                '$ref' => $this->registerReusableResponse($response),
            ];
        }

        return $responses;
    }

    protected function usesThrottleMiddleware(Collection $middleware): bool
    {
        return $middleware->contains(function (mixed $definition) {
            return is_string($definition)
                && ($definition === 'throttle' || Str::startsWith($definition, 'throttle:'));
        });
    }

    protected function buildPathParameters(array $definition): array
    {
        $parameters = [];

        foreach ($definition as $name => $parameterDescription) {
            $prop = Property::compile($name);

            $parameters[] = [
                'in' => 'path',
                'name' => $prop->name,
                'schema' => [
                    'type' => $prop->type,
                ],
                'required' => ! $prop->optional,
                'description' => $parameterDescription,
            ];
        }

        return $parameters;
    }

    protected function buildQueryParameters(array $definition): array
    {
        $parameters = [];

        foreach ($definition as $name => $queryDescription) {
            $prop = Property::compile($name);

            $queryProp = [
                'in' => 'query',
                'name' => $prop->name,
                'schema' => [
                    'type' => $prop->type,
                ],
                'description' => $queryDescription,
                'required' => $prop->nonNullable,
            ];

            if ($prop->isEnum()) {
                $queryProp['schema']['enum'] = $prop->enumCases();
            }

            $parameters[] = $queryProp;
        }

        return $parameters;
    }

    protected function assertPaginatorOverridesAreValid(RouteAttribute $description): void
    {
        if ($description->paginator && ! $description->paginatedResponse) {
            throw GeneratorException::withMessage('The [paginator] override requires [paginatedResponse].');
        }

        if ($description->cursorPaginator && ! $description->cursorPaginatedResponse) {
            throw GeneratorException::withMessage('The [cursorPaginator] override requires [cursorPaginatedResponse].');
        }
    }

    protected function assertSupportedErrorResponseCode(int $code): void
    {
        if (! in_array($code, [422, 429], true)) {
            throw GeneratorException::withMessage("The error response [{$code}] is not supported. Supported error responses are [422, 429].");
        }
    }

    /**
     * Add registered route to the document.
     */
    public function route(Route $route, RouteAttribute $description, ?string $method = null, ?string $path = null): static
    {
        if (! is_null($method)) {
            $method = Str::lower($method);

            if ($method === 'head' || $method === 'options') {
                return $this;
            }

            $this->addRoute($route, $description, $method, $path);

            return $this;
        }

        foreach ($route->methods() as $method) {
            $method = Str::lower($method);

            if ($method == 'head' || $method == 'options') {
                continue;
            }

            $this->addRoute($route, $description, $method, $path);
        }

        return $this;
    }

    public function build(): array
    {
        $document = ArrayBuilder::make([
            'openapi' => $this->version,
        ]);

        $components = ArrayBuilder::make();

        $components->setUnlessEmpty('securitySchemes', $this->securitySchemas);
        $components->setUnlessEmpty('schemas', collect($this->schemas)->sortKeys()->all());
        $components->setUnlessEmpty('responses', $this->responses);

        $document->setUnlessEmpty('info', $this->info);
        $document->setUnlessEmpty('servers', $this->servers);
        $document->setUnlessEmpty('tags', collect($this->tags)->sortKeys()->values()->all());
        $document->setUnlessEmpty('components', $components);

        $paths = collect($this->paths)->groupBy('url')->map(function (Collection $paths) {
            return $paths->keyBy('method')->map->def->all();
        })->all();

        $document->setUnlessEmpty('paths', $paths);

        return (array) $document;
    }

    public function toYaml(): string
    {
        return Yaml::dump($this->build(), 1000, 2, Yaml::DUMP_NUMERIC_KEY_AS_STRING | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }
}
