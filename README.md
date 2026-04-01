# stacktrace/inspec

`stacktrace/inspec` generates an OpenAPI 3 document from PHP attributes on Laravel controller actions and Fractal transformers.

It is route-aware at generation time: controller attributes are only documented when the annotated method also resolves to a registered Laravel route.

## Installation

```bash
composer require stacktrace/inspec
```

## How it works

The package revolves around `StackTrace\Inspec\Generator` and `StackTrace\Inspec\Document`.

```php
use StackTrace\Inspec\Generator;

$document = (new Generator(
    title: 'Example API',
    description: 'Public API documentation',
    version: '1.0.0',
    servers: [
        'Production' => 'https://api.example.com',
        'Local' => 'http://localhost:8000',
    ],
    paths: [
        app_path('Http/Controllers/Api'),
    ],
))->generate();

$yaml = $document->toYaml();
```

Generation currently works like this:

- `Generator` scans the configured controller paths for public methods with `#[StackTrace\Inspec\Route(...)]`.
- Each annotated method must also be registered as a Laravel route. Unregistered methods are skipped.
- Invokable controllers are supported through `__invoke`.
- Transformer schemas are collected from `#[Schema(...)]` on the transformer's `transform()` method.
- The bundled generator initializes `Document` with the `api` prefix, so generated paths strip a leading `/api`.

## Annotating controller routes

Import the route attribute in your controller:

```php
use StackTrace\Inspec\Route;
```

### Basic JSON endpoint

```php
<?php

namespace App\Http\Controllers\Api;

use StackTrace\Inspec\Route;

class ShowStatusController
{
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
}
```

This produces a single operation tagged with `Status`, a `summary`, and a `200` JSON response.

### Path and query parameters

```php
<?php

namespace App\Http\Controllers\Api;

use App\Transformers\UserTransformer;
use StackTrace\Inspec\Route;

class ListAccountUsersController
{
    #[Route(
        tags: ['Accounts', 'Users'],
        summary: 'List account users',
        route: [
            'account:string' => 'Account UUID',
        ],
        query: [
            'search:string' => 'Free-text search term',
            'status!:string|enum:active,disabled' => 'Required status filter',
            'include?:string' => 'Comma-separated includes',
        ],
        response: [
            'data:array' => UserTransformer::class,
        ],
    )]
    public function __invoke(string $account)
    {
        //
    }
}
```

Parameter behavior comes from the property DSL:

- Path parameters use `?` to determine whether the generated parameter is marked as required.
- Query parameters use `!` to determine whether the parameter is required.
- Query parameter enums are emitted when you add an `|enum:...` modifier.

### Request bodies and field markers

```php
<?php

namespace App\Http\Controllers\Api;

use App\Transformers\UserTransformer;
use StackTrace\Inspec\Route;

class CreateUserController
{
    #[Route(
        tags: 'Users',
        summary: 'Create a user',
        request: [
            'name:string' => 'Present in the payload and nullable in the generated schema',
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
            422 => 'Validation failed',
        ],
    )]
    public function __invoke()
    {
        //
    }
}
```

Notes:

- `responseCode` applies to the primary `response`, `paginatedResponse`, or `cursorPaginatedResponse`.
- When a request body is present, Inspec automatically adds a `422` response unless you define one yourself.
- `422` and `429` entries in `responses` are emitted as the shared `ErrorResponse` component. Other entries become description-only responses.

### Paginated and cursor-paginated responses

Use transformer class strings for paginated responses.

```php
<?php

namespace App\Http\Controllers\Api;

use App\Transformers\UserTransformer;
use StackTrace\Inspec\Route;

class ListUsersController
{
    #[Route(
        tags: 'Users',
        summary: 'List users',
        paginatedResponse: UserTransformer::class,
        paginatedMeta: [
            '@description' => 'Additional pagination metadata',
            'filters' => [
                'status?:string' => 'Applied status filter',
            ],
        ],
        defaultPerPage: 50,
    )]
    public function __invoke()
    {
        //
    }
}
```

```php
<?php

namespace App\Http\Controllers\Api;

use App\Transformers\UserTransformer;
use StackTrace\Inspec\Route;

class CursorUsersController
{
    #[Route(
        tags: 'Users',
        summary: 'List users with cursor pagination',
        cursorPaginatedResponse: UserTransformer::class,
        defaultPerPage: 100,
    )]
    public function __invoke()
    {
        //
    }
}
```

Pagination behavior:

- `paginatedResponse` adds `limit` and `page` query parameters.
- `cursorPaginatedResponse` adds `limit` and `cursor` query parameters.
- `defaultPerPage` only changes the text in the generated `limit` parameter description.
- `paginatedMeta` is merged into the generated `meta` object next to the built-in pagination block.

### Multipart and file uploads

```php
<?php

namespace App\Http\Controllers\Api;

use App\Transformers\AvatarTransformer;
use StackTrace\Inspec\Route;

class UploadAvatarController
{
    #[Route(
        tags: 'Avatars',
        summary: 'Upload a new avatar',
        multipart: true,
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
}
```

`file` fields automatically switch the request body content type to `multipart/form-data`. The `multipart` flag lets you force that content type even if there is no `file` field.

## DSL reference

Inspec parses field definitions with `StackTrace\Inspec\Property::compile()`. The general shape is:

```text
name[?][!][:type[,typeArg...]][|modifier:arg[,arg...]]
```

Examples:

| DSL | Meaning |
| --- | --- |
| `email:string` | Field named `email` with type `string` |
| `email!:string` | Marks the field as non-nullable |
| `email?:string` | Marks the field as optional |
| `email?!:string` | Marks the field as optional and non-nullable |
| `status:string|enum:draft,published` | String field limited to the given enum values |
| `tags:array,string` | Array of strings |
| `avatar:file` | Binary file upload |

### Primitive fields

Primitive definitions map the DSL type directly into the generated schema:

```php
[
    'id:string' => 'Resource UUID',
    'count:integer' => 'Number of items',
    'healthy:boolean' => 'Health status',
]
```

The generator does not maintain a hardcoded type whitelist. Whatever you put in the DSL is written as the OpenAPI `type`, except that `file` is translated to `type: string` with `format: binary`.

### Arrays

Use `array,<itemType>` for primitive arrays:

```php
[
    'tags:array,string' => 'List of tags',
    'scores:array,integer' => 'List of scores',
]
```

For arrays of transformer-backed objects, make the field itself an array and use the transformer class as the value:

```php
[
    'data:array' => \App\Transformers\UserTransformer::class,
]
```

### Enums

Add `|enum:...` to emit enum values:

```php
[
    'status:string|enum:draft,published,archived' => 'Current status',
]
```

You can also point `enum:` at a backed enum class name:

```php
[
    'status:string|enum:App\Enums\PostStatus' => 'Current status',
]
```

When the enum modifier contains a single backed enum class name, Inspec expands it to that enum's case values.

### Inline nested objects

If the array value is another array, Inspec builds an inline object:

```php
[
    'author' => [
        'id:string' => 'Author UUID',
        'name:string' => 'Author display name',
    ],
]
```

Two metadata keys are reserved for the current object:

```php
[
    'meta' => [
        '@description' => 'Extra metadata about the current result set',
        '@example' => [
            'requested_at' => '2026-04-01T12:00:00Z',
        ],
        'requested_at:string' => 'ISO-8601 timestamp',
    ],
]
```

- `@description` adds an object-level description.
- `@example` adds an object-level example.

### Transformer and schema references

If a field value is a transformer class string, Inspec resolves the transformer's `#[Schema(...)]` definition and emits a `$ref`:

```php
[
    'data' => \App\Transformers\UserTransformer::class,
]
```

`StackTrace\Inspec\Document` also supports `SchemaObject` references when you build objects programmatically.

### Request and response nullability rules

`Document::buildObject()` uses different rules depending on what is being built.

For request, response, and pagination-meta objects:

- `field` means "present" in the DSL and is emitted as nullable unless the type is `boolean`.
- `field!` means non-nullable.
- `field?` means optional and nullable.
- `field?!` means optional and non-nullable.

For schema objects created from `#[Schema(...)]`:

- `field` means a normal non-nullable schema field.
- `field?` means a normal schema field that is emitted as nullable.
- `!` does not have separate meaning for schema objects.

Current caveat: object schemas do not currently emit an OpenAPI `required` array, so `?` and `!` are best understood as Inspec's internal field markers rather than a complete requiredness implementation.

### Path and query parameter rules

Route and query parameters reuse the same DSL parser, but they are interpreted differently:

- Path parameters use `?` to decide whether the generated parameter is marked `required`.
- Query parameters use `!` to decide whether the generated parameter is marked `required`.
- Query parameter enums are emitted from `|enum:...`.
- Parameter descriptions come from the array values you provide in `route` and `query`.

Examples:

```php
[
    'account:string' => 'Required path parameter',
    'include?:string' => 'Optional query parameter',
    'status!:string|enum:active,disabled' => 'Required query parameter',
]
```

## Transformer schemas

Fractal transformers define reusable component schemas with `#[StackTrace\Inspec\Schema(...)]` on `transform()`.

```php
<?php

namespace App\Transformers;

use App\Models\User;
use League\Fractal\TransformerAbstract;
use StackTrace\Inspec\ExpandCollection;
use StackTrace\Inspec\ExpandItem;
use StackTrace\Inspec\Schema;

class UserTransformer extends TransformerAbstract
{
    protected array $availableIncludes = ['team', 'roles'];

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

Transformer schema behavior:

- The schema name defaults to the transformer class basename without the `Transformer` suffix.
- You can override the component name with `#[Schema(name: 'CustomName', object: [...])]`.
- `#[ExpandItem(...)]` and `#[ExpandCollection(...)]` are only considered on methods whose names start with `include`.
- The generated property name is derived from the method name in `snake_case`, so `includeTeam()` becomes `team`.
- Expanded relationships are emitted as objects with a nested `data` property.
- `ExpandItem` can also receive an array of transformer class strings, which is emitted as an `allOf` list under `data`.

## Auth and middleware-derived docs

Some documentation is inferred from the resolved Laravel route rather than the attribute itself:

- Routes with the `auth:sanctum` middleware receive `security: [{ bearerAuth: [] }]`.
- The `bearerAuth` security scheme is registered automatically by `Generator`.

## Current limitations

This README describes current behavior as implemented today:

- `Route::$description` exists on the attribute, but is not currently written into the generated OpenAPI operation.
- `paginatedResponse` and `cursorPaginatedResponse` are typed as `array|string|null`, but the current builder effectively supports transformer class strings only.
- Request and response object schemas do not currently emit an OpenAPI `required` array.
