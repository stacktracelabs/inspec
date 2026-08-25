<?php

use StackTrace\Inspec\Api;
use Workbench\App\Transformers\ReferencePropertiesTransformer;

function buildNullableReferenceDocument(): array
{
    return (new Api())
        ->name('nullable-references')
        ->withoutBroadcasting()
        ->get(
            '/users/me',
            tags: 'References',
            summary: 'Get reference properties',
            response: ReferencePropertiesTransformer::class,
        )
        ->toOpenAPI()
        ->build();
}

test('nullable schema class references use a schema wrapper', function () {
    $document = buildNullableReferenceDocument();
    $properties = $document['components']['schemas']['ReferenceProperties']['properties'];

    expect($document['openapi'])->toBe('3.0.0')
        ->and($properties['nullable_schema'])->toBe([
            'allOf' => [
                ['$ref' => '#/components/schemas/Price'],
            ],
            'nullable' => true,
        ])
        ->and($properties['schema'])->toBe([
            '$ref' => '#/components/schemas/Price',
        ]);
});

test('nullable transformer references use a schema wrapper', function () {
    $properties = buildNullableReferenceDocument()['components']['schemas']['ReferenceProperties']['properties'];

    expect($properties['nullable_transformer'])->toBe([
        'allOf' => [
            ['$ref' => '#/components/schemas/User'],
        ],
        'nullable' => true,
    ])->and($properties['transformer'])->toBe([
        '$ref' => '#/components/schemas/User',
    ]);
});

test('description based schema and transformer references preserve nullability', function () {
    $properties = buildNullableReferenceDocument()['components']['schemas']['ReferenceProperties']['properties'];

    expect($properties['nullable_schema_value'])->toBe([
        'allOf' => [
            ['$ref' => '#/components/schemas/Price'],
        ],
        'nullable' => true,
    ])->and($properties['schema_value'])->toBe([
        '$ref' => '#/components/schemas/Price',
    ])->and($properties['nullable_transformer_value'])->toBe([
        'allOf' => [
            ['$ref' => '#/components/schemas/User'],
        ],
        'nullable' => true,
    ])->and($properties['transformer_value'])->toBe([
        '$ref' => '#/components/schemas/User',
    ]);
});
