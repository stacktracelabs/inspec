---
name: annotate-inspec-api
description: Add or update `stacktrace/inspec` documentation attributes in Laravel APIs by annotating controller actions with `#[StackTrace\\Inspec\\Route(...)]`, mapping request/query/response shapes into the Inspec property DSL, and keeping Fractal transformer `#[Schema]`, `#[ExpandItem]`, and `#[ExpandCollection]` metadata in sync. Use to document endpoints, add OpenAPI annotations, migrate manual docs into Inspec attributes, or fix generated spec gaps in a Laravel project that uses Inspec.
---

# Annotate Inspec API

## Overview
Document endpoints with Inspec attributes, not hand-written YAML. Work from the real Laravel route and the current request/response behavior, then express that behavior with `#[Route(...)]` on the controller action and transformer metadata where needed.

## Workflow
1. Find the resolved Laravel route and the exact public controller method that handles it. Inspec only documents annotated public methods that also resolve to registered routes; invokable controllers use `__invoke`.
2. Add or update `use StackTrace\Inspec\Route;` and place `#[Route(...)]` directly on the action method.
3. Model the endpoint with the smallest accurate shape:
   - `route` for path parameters
   - `query` for query parameters
   - `request` for request bodies
   - `response` for standard success bodies
   - `paginatedResponse` or `cursorPaginatedResponse` for transformer-backed collections
   - `operation: new Operation(...)` when a manual route should be authored or customized as a reusable object
4. If a response points to a Fractal transformer class, ensure its `transform()` method has `#[Schema(...)]`. If the transformer exposes includes, annotate `include*` methods with `#[ExpandItem]` or `#[ExpandCollection]`.
5. Verify generation with `php artisan inspec:generate --stdout` when available. Prefer narrowing to the routes you touched with `--api`, `--path`, `--route`, and `--method` so you can inspect the generated YAML without rewriting files.

## Controller Rules
- Prefer `summary` and `tags`. Use `tags` as a string or array.
- Set `responseCode` explicitly for non-200 success responses, especially `201` for create endpoints.
- Use `additionalResponses` for route-specific extra statuses or inferred-error overrides. Values may be `null`, plain strings, `Response` instances, or `Response` class strings.
- Use `deprecated: true` for deprecated endpoints.
- Use `multipart: true` when the endpoint is multipart even if no field uses the `file` type.
- Do not rely on `description` yet. The attribute accepts it, but the generator does not emit it currently.
- Do not annotate helper methods that are not bound to Laravel routes. The generator skips them.

## DSL Rules
- Read `references/inspec-annotation-reference.md` before writing non-trivial request or response bodies.
- Use `name[?][!]:type[,typeArg...][|modifier:arg[,arg...]]`.
- Remember that `?` and `!` change meaning by context:
  - In `request`, `response`, and paginator `meta` objects, `?` means optional and `!` means non-nullable.
  - In `route` parameters, `?` controls requiredness. Path parameters are usually not optional.
  - In `query` parameters, `!` controls requiredness.
  - In transformer `#[Schema(...)]` objects, `?` makes the field nullable; Inspec does not currently emit a `required` array for schema objects.
- Use `array,<itemType>` for primitive arrays, or use `'data:array' => UserTransformer::class` for arrays of transformer-backed objects.
- Use `|enum:a,b,c` or `|enum:App\\Enums\\BackedEnum`.
- Use `file` for uploads; it becomes `type: string` with `format: binary`.

## Transformer Rules
- Put `#[Schema(object: [...])]` on the transformer's `transform()` method.
- The schema name defaults to the transformer class basename without `Transformer`; override with `name:` only when needed.
- Only `include*` methods are considered for expands.
- `includeTeam()` becomes the `team` property in the generated schema.
- `ExpandItem([A::class, B::class])` produces `allOf` under `data`.

## Current Behavior To Respect
- `Api` enables Sanctum and broadcasting integrations by default. Use `withoutSanctum()` or `withoutBroadcasting()` when the generated spec should opt out.
- With Sanctum enabled, routes using `auth:sanctum` automatically receive `bearerAuth`; do not model that in the attribute.
- With broadcasting enabled, Inspec auto-documents the registered Pusher-related broadcasting auth routes when they exist.
- `withBroadcasting(fn (Operation $operation, Route $route) => ...)` can customize those auto-documented broadcasting operations or return `null` to skip one.
- A request body automatically adds a `422` validation response unless `additionalResponses[422]` or API-level error-response configuration overrides it.
- Routes with `throttle` middleware automatically add a `429` response unless `additionalResponses[429]` or API-level error-response configuration overrides it.
- API-wide inferred error defaults live on `Api::withValidationErrorResponse()`, `Api::withoutValidationErrorResponse()`, `Api::withTooManyRequestsResponse()`, and `Api::withoutTooManyRequestsResponse()`.
- `paginatedResponse` and `cursorPaginatedResponse` currently work with transformer class strings, not inline object arrays.
- API-wide pagination defaults live on `Api::withPagination()` and `Api::withCursorPagination()`.
- The paginator owns query params, paginator schema/meta, and the generated paginated success response. If the envelope needs to change, use a custom paginator subclass or paginator response-metadata helpers on the API-level paginator definition.
- Request and response requiredness is not fully represented as OpenAPI `required` arrays yet, so keep the DSL truthful to app behavior but expect that limitation.
- Use `Api::prefix('api')` when Laravel routes live under `/api` but generated paths should omit that prefix.
- When an API uses `prefix(...)`, match generated paths in `--path` filters, for example `^/users` instead of `^/api/users`.
- Verification-first command examples:
  - `php artisan inspec:generate --api=App\\OpenApi\\WebhookDocumentation --stdout`
  - `php artisan inspec:generate --api=webhooks --stdout --path='^/webhooks' --method=POST`
  - `php artisan inspec:generate --api=webhooks --stdout --route=webhooks.receive`
- Avoid regenerating files on disk unless the user explicitly asked for the output files to be written.

## Reference
Use `references/inspec-annotation-reference.md` for the full `#[Route]` argument guide, DSL syntax, context-specific marker semantics, transformer patterns, and current caveats.
