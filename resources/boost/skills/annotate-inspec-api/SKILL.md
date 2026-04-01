---
name: annotate-inspec-api
description: Add or update `stacktrace/inspec` documentation attributes in Laravel APIs by annotating controller actions with `#[StackTrace\\Inspec\\Route(...)]`, mapping request/query/response shapes into the Inspec property DSL, and keeping Fractal transformer `#[Schema]`, `#[ExpandItem]`, and `#[ExpandCollection]` metadata in sync. Use when Codex is asked to document endpoints, add OpenAPI annotations, migrate manual docs into Inspec attributes, or fix generated spec gaps in a Laravel project that uses Inspec.
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
4. If a response points to a Fractal transformer class, ensure its `transform()` method has `#[Schema(...)]`. If the transformer exposes includes, annotate `include*` methods with `#[ExpandItem]` or `#[ExpandCollection]`.
5. Regenerate the spec with `php artisan inspec:generate` when available and spot-check the affected path in the YAML.

## Controller Rules
- Prefer `summary` and `tags`. Use `tags` as a string or array.
- Set `responseCode` explicitly for non-200 success responses, especially `201` for create endpoints.
- Use `additionalResponses` for other status codes. Only `422` and `429` become the shared `ErrorResponse`; other codes are description-only responses.
- Use `deprecated: true` for deprecated endpoints.
- Use `multipart: true` when the endpoint is multipart even if no field uses the `file` type.
- Do not rely on `description` yet. The attribute accepts it, but the generator does not emit it currently.
- Do not annotate helper methods that are not bound to Laravel routes. The generator skips them.

## DSL Rules
- Read `references/inspec-annotation-reference.md` before writing non-trivial request or response bodies.
- Use `name[?][!]:type[,typeArg...][|modifier:arg[,arg...]]`.
- Remember that `?` and `!` change meaning by context:
  - In `request`, `response`, and `paginatedMeta` objects, `?` means optional and `!` means non-nullable.
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
- Routes with `auth:sanctum` middleware automatically receive `bearerAuth`; do not model that in the attribute.
- A request body automatically adds a `422` response unless one is already present.
- `paginatedResponse` and `cursorPaginatedResponse` currently work with transformer class strings, not inline object arrays.
- Request and response requiredness is not fully represented as OpenAPI `required` arrays yet, so keep the DSL truthful to app behavior but expect that limitation.
- The default generator strips a leading `/api` prefix from output paths.

## Reference
Use `references/inspec-annotation-reference.md` for the full `#[Route]` argument guide, DSL syntax, context-specific marker semantics, transformer patterns, and current caveats.
