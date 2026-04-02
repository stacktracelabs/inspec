<?php


namespace StackTrace\Inspec;


use StackTrace\Inspec\Route as RouteAttribute;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\Fractal\TransformerAbstract;
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

    protected function buildCursorPaginatedResponse(array|string $def, ?array $metaBlueprint = null): array
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

        $this->schema('CursorPaginator', $this->buildObject([
            'current?:string' => 'Currently applied cursor',
            'prev?:string' => 'The previous cursor',
            'next?:string' => 'The next cursor',
            'count:integer' => 'Total number of results on current page',
        ]));

        $properties = [
            'cursor' => [
                '$ref' => '#/components/schemas/CursorPaginator',
            ],
        ];

        if ($metaBlueprint) {
            $customMeta = Arr::get($this->buildObject($metaBlueprint), 'properties');

            if ($customMeta) {
                $properties = array_merge($properties, $customMeta);
            }
        }

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
                                'properties' => $properties
                            ],
                        ]
                    ]
                ]
            ]
        ];
    }

    protected function buildPaginatedResponse(array|string $def, ?array $metaBlueprint = null): array
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

        $this->schema('Paginator', $this->buildObject([
            'total:integer' => 'Total number of results',
            'count:integer' => 'Number of results on current page',
            'per_page:integer' => 'Number of results per single page',
            'current_page:integer' => 'Current page number (starting with 1 as first page)',
            'total_pages:integer' => 'Total number of pages',
            'links' => [
                'next?:string' => 'Link to the next page if available',
            ]
        ]));

        $properties = [
            'pagination' => [
                '$ref' => '#/components/schemas/Paginator',
            ]
        ];

        if ($metaBlueprint) {
            $customMeta = Arr::get($this->buildObject($metaBlueprint), 'properties');

            if ($customMeta) {
                $properties = array_merge($properties, $customMeta);
            }
        }

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
                                'properties' => $properties
                            ],
                        ]
                    ]
                ]
            ]
        ];
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

    protected function addRoute(Route $route, RouteAttribute $description, string $method, ?string $path = null): static
    {
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
            // TODO: Can check for undocumented parameters and issue a warning.
            foreach ($description->route as $name => $parameterDescription) {
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
        }

        if (! empty($description->query)) {
            foreach ($description->query as $name => $queryDescription) {
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
        }

        // Security
        $middleware = collect($route->gatherMiddleware());
        if ($middleware->contains('auth:sanctum')) {
            $path['security'] = [
                [
                    'bearerAuth' => []
                ]
            ];
        }

        if ($description->cursorPaginatedResponse || $description->paginatedResponse) {
            $parameters[] = [
                'in' => 'query',
                'name' => 'limit',
                'schema' => [
                    'type' => 'integer',
                ],
                'required' => false,
                'description' => "Number of results to return. Defaults to ". (is_null($description->defaultPerPage) ? 15 : $description->defaultPerPage),
            ];
        }

        if ($description->cursorPaginatedResponse) {
            $parameters[] = [
                'in' => 'query',
                'name' => 'cursor',
                'schema' => [
                    'type' => 'string',
                ],
                'required' => false,
                'description' => 'The pagination cursor value',
            ];
        }

        if ($description->paginatedResponse) {
            $parameters[] = [
                'in' => 'query',
                'name' => 'page',
                'schema' => [
                    'type' => 'integer',
                ],
                'required' => false,
                'description' => 'The page number',
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
        if (is_array($description->response) && !empty($description->response)) {
            Arr::set($path, "responses.{$description->responseCode}", $this->buildResponse($description->response));
        } else if ($description->paginatedResponse != null) {
            Arr::set($path, "responses.{$description->responseCode}", $this->buildPaginatedResponse($description->paginatedResponse, $description->paginatedMeta));
        } else if ($description->cursorPaginatedResponse != null) {
            Arr::set($path, "responses.{$description->responseCode}", $this->buildCursorPaginatedResponse($description->cursorPaginatedResponse, $description->paginatedMeta));
        }

        // Other responses
        if (! empty($description->additionalResponses)) {
            foreach ($description->additionalResponses as $code => $block) {
                if ($code == 422 || $code == 429) {
                    Arr::set($path, "responses.{$code}", [
                        '$ref' => '#/components/responses/ErrorResponse',
                    ]);
                } else {
                    Arr::set($path, "responses.{$code}", [
                        'description' => $block,
                    ]);
                }
            }
        }

        // If request accepts some request body, we can assume that validation is done of that body,
        // and 422 code may be returned when the provided payload is invalid.
        if (is_array($description->request) && !empty($description->request) && !Arr::has($path, 'responses.422')) {
            Arr::set($path, "responses.422", [
                '$ref' => '#/components/responses/ErrorResponse',
            ]);
        }

        $endpoint = [
            'url' => $url,
            'method' => $method,
            'def' => (array) $path,
        ];

        $this->paths[] = $endpoint;

        return $this;
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
