# Inspec Annotation Reference

## Contents
- Route-first workflow
- `#[Route(...)]` arguments
- Property DSL
- Marker semantics by context
- Common patterns
- Transformer schemas and expands
- Generated behavior and caveats

## Route-first workflow
Start from the real Laravel route, not from a guessed spec shape.

1. Find the registered route URI, methods, middleware, and controller action.
2. Annotate the exact public method that Laravel resolves.
3. Keep the annotation faithful to current behavior. Do not invent fields that the controller or transformer does not return.
4. If the response uses a Fractal transformer, keep the controller attribute and transformer schema consistent.

Inspec only documents annotated public methods that also resolve to registered Laravel routes. Invokable controllers are matched through `__invoke`.

## `#[Route(...)]` arguments
Use `StackTrace\Inspec\Route` on controller actions.

```php
use StackTrace\Inspec\Route;
```

Available arguments:

- `tags: string|array`
  Group names for the operation.
- `summary: string`
  Short operation summary. This is the most important user-facing text today.
- `description: string`
  Stored on the attribute, but not currently emitted into the generated operation.
- `route: array`
  Path parameters using the property DSL.
- `query: array`
  Query parameters using the property DSL.
- `request: ?array`
  Request body object definition.
- `response: ?array`
  Primary success response object definition.
- `paginatedResponse: array|string|null`
  Paginated `data` collection. In practice, use a transformer class string.
- `cursorPaginatedResponse: array|string|null`
  Cursor-paginated `data` collection. In practice, use a transformer class string.
- `paginator: ?LengthAwarePaginator`
  Replaces the API-level page paginator definition for this route.
- `responseCode: int`
  Status code for the primary success response. Defaults to `200`.
- `additionalResponses: array`
  Extra status codes and inferred-error overrides. Values may be `null`, plain strings, `Response` instances, or `Response` class strings.
- `cursorPaginator: ?CursorPaginator`
  Replaces the API-level cursor paginator definition for this route.
- `deprecated: bool`
  Marks the operation as deprecated.
- `multipart: bool`
  Forces `multipart/form-data` even when no field uses the `file` type.

## Property DSL
General form:

```text
name[?][!][:type[,typeArg...]][|modifier:arg[,arg...]]
```

Common examples:

| DSL | Meaning |
| --- | --- |
| `email:string` | Field named `email` with type `string` |
| `email!:string` | Non-nullable field |
| `email?:string` | Optional field |
| `email?!:string` | Optional and non-nullable field |
| `status:string|enum:draft,published` | String field limited to enum values |
| `status:string|enum:App\\Enums\\PostStatus` | Enum values expanded from a backed enum |
| `tags:array,string` | Array of strings |
| `avatar:file` | Binary file upload |

Primitive types are written straight through to the generated OpenAPI `type`. Inspec does not maintain a hardcoded whitelist. The special case is `file`, which becomes:

```yaml
type: string
format: binary
```

## Marker semantics by context
The same DSL markers do different jobs depending on where you use them.

### Request, response, and paginator-meta objects
- `field`
  Present in the payload conceptually; emitted as nullable unless the type is `boolean`.
- `field!`
  Present and non-nullable.
- `field?`
  Optional and nullable.
- `field?!`
  Optional and non-nullable.

Current caveat: Inspec does not currently emit a full OpenAPI `required` array for these objects, so `?` and `!` are best treated as Inspec field markers that express intent rather than perfect requiredness output.

### Transformer `#[Schema(...)]` objects
- `field`
  Normal non-nullable schema field.
- `field?`
  Nullable schema field.
- `!`
  Does not have a separate meaning for schema objects.

Current caveat: schema objects also do not emit a `required` array.

### Route parameters
- `?` controls whether the generated path parameter is marked `required`.
- `!` does not control path requiredness.

In practice, path parameters are usually required, so do not add `?` unless the route shape truly warrants it.

### Query parameters
- `!` controls whether the generated query parameter is marked `required`.
- `?` does not control query requiredness.
- `|enum:...` emits an enum on the parameter schema.

This is a common gotcha:

```php
query: [
    'status!:string|enum:active,disabled' => 'Required status filter',
    'include?:string' => 'Optional include list',
],
```

## Common patterns

### Basic JSON endpoint

```php
#[Route(
    tags: 'Status',
    summary: 'Show API status',
    response: [
        'name:string' => 'Application name',
        'version:string' => 'Current API version',
        'healthy:boolean' => 'Whether the API is healthy',
    ],
)]
public function __invoke()
{
    //
}
```

### Request body with nested object

```php
#[Route(
    tags: 'Users',
    summary: 'Create a user',
    request: [
        'name:string' => 'Present and nullable',
        'email!:string' => 'Present and non-nullable',
        'nickname?:string' => 'Optional and nullable',
        'timezone?!:string' => 'Optional and non-nullable',
        'role:string|enum:admin,member' => 'Assigned role',
        'profile' => [
            '@description' => 'Nested profile payload',
            '@example' => [
                'bio' => 'Builder and API enthusiast',
            ],
            'bio?:string' => 'Short biography',
        ],
    ],
    responseCode: 201,
    response: [
        'data' => UserTransformer::class,
    ],
    additionalResponses: [
        401 => 'Unauthenticated',
        422 => null,
    ],
)]
public function __invoke()
{
    //
}
```

Reserved object metadata keys:

- `@description`
  Adds an object-level description.
- `@example`
  Adds an object-level example.

### Arrays
Use primitive arrays directly:

```php
[
    'tags:array,string' => 'List of tags',
]
```

Use a transformer class as the value for arrays of objects:

```php
[
    'data:array' => UserTransformer::class,
]
```

Use a transformer class as the value for a single object reference:

```php
[
    'data' => UserTransformer::class,
]
```

### Paginated responses

```php
use StackTrace\Inspec\Paginators\LengthAwarePaginator;

#[Route(
    tags: 'Users',
    summary: 'List users',
    paginatedResponse: UserTransformer::class,
    paginator: new LengthAwarePaginator(
        meta: [
            'filters' => [
                'status?:string' => 'Applied status filter',
            ],
        ],
        defaultPerPage: 50,
    ),
)]
public function __invoke()
{
    //
}
```

Generated pagination parameter behavior:

- `paginatedResponse` uses the active `LengthAwarePaginator` definition.
- `cursorPaginatedResponse` uses the active `CursorPaginator` definition.
- Use `Api::withPagination()` and `Api::withCursorPagination()` for API-wide defaults.
- Use `paginator` and `cursorPaginator` when a single route needs different pagination metadata or query parameters.
- In PHP attributes, paginator overrides must be passed as constructor arguments like `new LengthAwarePaginator(...)`.

### Multipart uploads

```php
#[Route(
    tags: 'Avatars',
    summary: 'Upload a new avatar',
    request: [
        'avatar:file' => 'Image file to upload',
        'alt_text?:string' => 'Optional alt text',
    ],
    responseCode: 201,
    response: [
        'data' => AvatarTransformer::class,
    ],
)]
public function __invoke()
{
    //
}
```

`file` fields automatically switch the request body to `multipart/form-data`. Use `multipart: true` when the payload should still be multipart without a `file` field.

## Transformer schemas and expands
Fractal transformers define reusable component schemas with `#[StackTrace\Inspec\Schema(...)]` on `transform()`.

```php
use StackTrace\Inspec\ExpandCollection;
use StackTrace\Inspec\ExpandItem;
use StackTrace\Inspec\Schema;

class UserTransformer extends TransformerAbstract
{
    #[Schema(
        object: [
            'id:string' => 'User UUID',
            'name:string' => 'Display name',
            'email?:string' => 'Email address',
        ],
    )]
    public function transform(User $user): array
    {
        return [];
    }

    #[ExpandItem(TeamTransformer::class)]
    public function includeTeam(User $user)
    {
        //
    }

    #[ExpandCollection(RoleTransformer::class)]
    public function includeRoles(User $user)
    {
        //
    }
}
```

Rules:

- The schema name defaults to the transformer class basename without `Transformer`.
- Override the component name with `#[Schema(name: 'CustomName', object: [...])]` only when needed.
- Only `include*` methods are considered for expands.
- `includeTeam()` becomes the `team` property in snake case.
- Expanded relationships are emitted as objects with nested `data`.
- `ExpandItem([A::class, B::class])` produces `allOf` under `data`.

## Generated behavior and caveats
- `Api` enables Sanctum and broadcasting integrations by default. Use `withoutSanctum()` or `withoutBroadcasting()` to opt out for a specific spec.
- With Sanctum enabled, routes with `auth:sanctum` middleware automatically receive `security: [{ bearerAuth: [] }]`.
- The generator registers the `bearerAuth` security scheme only when Sanctum is enabled and at least one included route actually uses it.
- With broadcasting enabled, the registered Pusher-related broadcasting auth routes are auto-documented when present.
- If a request body exists, Inspec infers a `422` validation response unless route-level or API-level configuration disables or replaces it.
- If a route uses `throttle` middleware, Inspec infers a `429` too-many-requests response unless route-level or API-level configuration disables or replaces it.
- `additionalResponses` accepts `null`, plain strings, `Response` instances, and `Response` class strings.
- `paginatedResponse` and `cursorPaginatedResponse` are typed as `array|string|null`, but the current builder effectively supports transformer class strings only.
- The route attribute's `description` is stored but not currently emitted into the OpenAPI operation.
- Boolean fields never receive `nullable: true`, even when other field types would.
- Generated paths only strip a Laravel route prefix when the documentation explicitly calls `Api::prefix(...)`.
- Path filters and `inspec:generate --path=...` always match the final generated path after any configured prefix stripping.
