# AGENTS.md

## Project Overview

`stacktrace/inspec` is a Laravel package that generates an OpenAPI 3 specification from PHP attributes placed on:

- API controller actions
- Fractal transformers
- transformer include methods for expandable relationships

The package is consumer-app aware at generation time: it inspects registered Laravel routes, reflects on controller methods, and builds a YAML document from attribute metadata.

## Stack

- PHP `^8.4`
- Laravel `^11.0|^12.0|^13.0`
- `league/fractal`
- `symfony/yaml`
- Pest + Orchestra Testbench for tests

## Repo Map

- `src/Generator.php`
  Discovers annotated controller methods, resolves them to registered Laravel routes, and seeds the OpenAPI document.

- `src/Document.php`
  Core OpenAPI builder. Handles request/response blocks, pagination, security schemes, schema extraction, and YAML serialization.

- `src/Route.php`
  Main controller action attribute for describing endpoint metadata.

- `src/Schema.php`
  Attribute placed on Fractal transformer `transform()` methods to describe object schemas.

- `src/ExpandItem.php` and `src/ExpandCollection.php`
  Attributes for Fractal `include*` methods that expose expandable nested resources.

- `src/Property.php`
  Parser for the compact property DSL used in request/response/schema definitions.

- `src/SchemaObject.php`
  Small reusable schema fragment object for inline or referenced object definitions.

- `src/Commands/Generate.php`
  Console entrypoint for spec generation. It is currently a scaffold and not fully implemented.

- `workbench/`
  Orchestra Testbench Laravel app used for routes, fixtures, and package integration testing.

- `tests/`
  Pest test suite. Coverage is minimal today, so new features should usually add integration tests.

## Core Concepts

### Controller annotation

Public controller methods are documented with `#[StackTrace\Inspec\Route(...)]`.

Important detail: generation does not rely on annotations alone. `Generator` resolves annotated methods against the app's registered Laravel routes, so tests should register realistic routes in the Testbench workbench.

### Transformer annotation

Fractal transformers describe schema output with `#[Schema(...)]` on the `transform()` method. The schema name defaults to the transformer class basename without the `Transformer` suffix unless overridden.

### Expandable relationships

`#[ExpandItem]` and `#[ExpandCollection]` on `include*` methods extend the generated schema with nested `data` blocks.

### Property DSL

Request, response, and schema arrays use a compact string syntax parsed by `Property::compile()`.

Examples:

- `'email:string' => 'Email address'`
- `'email!:string' => 'Required and non-nullable in requests'`
- `'email?:string' => 'Optional and nullable in requests'`
- `'status:string|enum:draft,published' => 'Status value'`
- `'items:array,string' => 'List of strings'`

`Document::buildObject()` is the main place to understand how this DSL maps to OpenAPI fields.

## Commands

- `composer test`
  Clears the Testbench skeleton and runs Pest.

- `composer build`
  Builds the workbench app and refreshes the sqlite database.

- `composer serve`
  Builds the workbench and serves the package demo app.

- `composer prepare`
  Re-runs Testbench package discovery.

- `php vendor/bin/pest --filter <name>`
  Useful for iterating on a focused test.

## Working Agreements

- Prefer package-level integration tests over narrow unit mocks when behavior depends on Laravel routes, middleware, Fractal transformers, or generated YAML.
- Keep the package app-agnostic. If touching consumer-specific integrations, make them optional or guarded.
- Preserve the attribute-driven developer experience. New features should feel natural to express in controller and transformer attributes.
- Keep generated output deterministic where possible. Stable ordering helps testing and review.
- Match the style of the file you are editing. Avoid repo-wide formatting churn unless the task explicitly asks for it.
- Favor additive changes and small helpers over large rewrites, especially in `Document.php`, which already concentrates most generation logic.

## Testing Guidance

When changing generation behavior:

1. Add or update a workbench fixture that mirrors real package usage.
2. Add a Pest test that exercises the generated OpenAPI structure or YAML.
3. Run `composer test`.
4. Run `composer build` as well if the change depends on workbench routes, config, or database state.

Good test targets:

- route discovery for controller methods and invokable controllers
- request body generation
- standard response generation
- paginated and cursor-paginated response blocks
- Fractal transformer schema extraction
- expanded includes via `ExpandItem` and `ExpandCollection`
- enum, array, nullable, and file field handling in the property DSL
