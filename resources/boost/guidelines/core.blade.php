## Inspec

- `stacktrace/inspec` generates an OpenAPI 3 document from PHP attributes on Laravel controller actions and Fractal transformers.
- Prefer Inspec's attribute-driven workflow over hand-written OpenAPI YAML when documenting endpoints.
- Use the bundled `annotate-inspec-api` Boost skill when working on non-trivial Inspec annotations, property DSL changes, transformer schemas, or generated spec issues.

### Conventions

- Annotate the exact public controller method Laravel resolves for the route with `#[StackTrace\Inspec\Route(...)]`.
- Inspec only documents annotated methods that are also registered Laravel routes. Invokable controllers use `__invoke`.
- Put reusable response schemas on Fractal transformers with `#[StackTrace\Inspec\Schema(...)]` on `transform()`.
- Annotate transformer `include*` methods with `#[ExpandItem]` or `#[ExpandCollection]` for expandable nested resources.
- Configure package defaults in `config/inspec.php`. Important keys are `title`, `description`, `version`, `servers`, `paths`, and `output`.

### Common Workflow

1. Publish or edit the Inspec config:

```bash
php artisan vendor:publish --tag=inspec-config
```

2. Document controller actions with `#[Route(...)]`:

```php
use App\Transformers\UserTransformer;
use StackTrace\Inspec\Route;

class ShowUserController
{
    #[Route(
        tags: 'Users',
        summary: 'Show a user',
        route: [
            'user:string' => 'User UUID',
        ],
        query: [
            'include?:string' => 'Comma-separated includes',
        ],
        response: [
            'data' => UserTransformer::class,
        ],
        additionalResponses: [
            404 => 'User not found',
        ],
    )]
    public function __invoke(string $user)
    {
        //
    }
}
```

3. Add transformer schemas when a response points to a transformer:

```php
use StackTrace\Inspec\Schema;

class UserTransformer extends TransformerAbstract
{
    #[Schema(object: [
        'id:string' => 'User UUID',
        'name:string' => 'Display name',
        'email?:string' => 'Email address',
    ])]
    public function transform(User $user): array
    {
        return [];
    }
}
```

4. Generate the spec:

```bash
php artisan inspec:generate
php artisan inspec:generate docs/openapi.yaml
```

### Property DSL

- Use `name[?][!]:type[,typeArg...][|modifier:arg[,arg...]]`.
- Use `route` for path parameters, `query` for query parameters, `request` for request bodies, and `response` for standard success payloads.
- Common examples:
  - `'email:string' => 'Email address'`
  - `'email!:string' => 'Present and non-nullable'`
  - `'nickname?:string' => 'Optional and nullable'`
  - `'status:string|enum:active,disabled' => 'Allowed values'`
  - `'tags:array,string' => 'List of tags'`
  - `'avatar:file' => 'Uploaded file'`
- In `query`, `!` controls requiredness. In `route`, `?` controls requiredness. In request and response objects, `?` and `!` are Inspec field markers for optionality and nullability.

### Best Practices

- Keep annotations faithful to actual controller and transformer behavior. Do not invent fields that are not returned or accepted.
- Use `responseCode: 201` for create endpoints and `additionalResponses` for extra status codes.
- Use `paginatedResponse` or `cursorPaginatedResponse` with transformer class strings for paginated collections.
- Use `multipart: true` or `file` fields for multipart uploads.
- Do not rely on `Route::$description` being emitted yet; prefer `summary`.
- Routes with `auth:sanctum` automatically receive bearer auth security in the generated spec.
