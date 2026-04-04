<?php

use StackTrace\Inspec\Api;
use Workbench\App\Transformers\PostTransformer;
use Workbench\App\Transformers\TagTransformer;
use Workbench\App\Transformers\TeamTransformer;
use Workbench\App\Transformers\UserTransformer;

test('ExpandItem produces a nested data object with a ref to the transformer schema', function () {
    $document = (new Api())
        ->name('expand-item')
        ->withoutBroadcasting()
        ->get(
            '/posts',
            tags: 'Posts',
            summary: 'List posts',
            response: [
                'data:array' => PostTransformer::class,
            ],
        )
        ->toOpenAPI()
        ->build();

    $schemas = $document['components']['schemas'];

    expect($schemas)->toHaveKey('Post')
        ->and($schemas['Post']['properties'])->toHaveKey('author')
        ->and($schemas['Post']['properties']['author']['type'])->toBe('object')
        ->and($schemas['Post']['properties']['author']['properties']['data']['$ref'])->toBe('#/components/schemas/User');
});

test('ExpandCollection produces a nested data array with item refs to the transformer schema', function () {
    $document = (new Api())
        ->name('expand-collection')
        ->withoutBroadcasting()
        ->get(
            '/posts',
            tags: 'Posts',
            summary: 'List posts',
            response: [
                'data:array' => PostTransformer::class,
            ],
        )
        ->toOpenAPI()
        ->build();

    $schemas = $document['components']['schemas'];

    expect($schemas)->toHaveKey('Post')
        ->and($schemas['Post']['properties'])->toHaveKey('tags')
        ->and($schemas['Post']['properties']['tags']['type'])->toBe('object')
        ->and($schemas['Post']['properties']['tags']['properties']['data']['type'])->toBe('array')
        ->and($schemas['Post']['properties']['tags']['properties']['data']['items']['$ref'])->toBe('#/components/schemas/Tag');
});

test('ExpandItem with an array of transformers produces an allOf under data', function () {
    $document = (new Api())
        ->name('expand-item-allof')
        ->withoutBroadcasting()
        ->get(
            '/posts',
            tags: 'Posts',
            summary: 'List posts',
            response: [
                'data:array' => PostTransformer::class,
            ],
        )
        ->toOpenAPI()
        ->build();

    $schemas = $document['components']['schemas'];

    expect($schemas)->toHaveKey('Post')
        ->and($schemas['Post']['properties'])->toHaveKey('co_authors')
        ->and($schemas['Post']['properties']['co_authors']['type'])->toBe('object')
        ->and($schemas['Post']['properties']['co_authors']['properties']['data']['allOf'])->toBe([
            ['$ref' => '#/components/schemas/User'],
            ['$ref' => '#/components/schemas/Team'],
        ]);
});

test('expanded transformer schemas are registered as reusable components', function () {
    $document = (new Api())
        ->name('expand-registers-schemas')
        ->withoutBroadcasting()
        ->get(
            '/posts',
            tags: 'Posts',
            summary: 'List posts',
            response: [
                'data:array' => PostTransformer::class,
            ],
        )
        ->toOpenAPI()
        ->build();

    $schemas = $document['components']['schemas'];

    expect($schemas)->toHaveKey('User')
        ->and($schemas)->toHaveKey('Tag')
        ->and($schemas)->toHaveKey('Team');
});

test('include methods not prefixed with include are ignored', function () {
    $document = (new Api())
        ->name('expand-method-prefix')
        ->withoutBroadcasting()
        ->get(
            '/posts',
            tags: 'Posts',
            summary: 'List posts',
            response: [
                'data:array' => PostTransformer::class,
            ],
        )
        ->toOpenAPI()
        ->build();

    $postSchema = $document['components']['schemas']['Post'];

    // Only include* methods should produce expand properties.
    // transform(), helperMethods, etc. should not appear.
    $expandPropertyNames = array_keys($postSchema['properties']);

    expect($expandPropertyNames)->toContain('author')
        ->and($expandPropertyNames)->toContain('tags')
        ->and($expandPropertyNames)->toContain('co_authors')
        ->and($expandPropertyNames)->not->toContain('transform');
});
