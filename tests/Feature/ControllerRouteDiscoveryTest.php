<?php

use Illuminate\Support\Facades\Route as LaravelRoute;
use StackTrace\Inspec\Api;
use Workbench\App\Http\Controllers\Api\ReusedRouteController;

test('it documents all routes that reuse an invokable controller', function () {
    LaravelRoute::get('api/reused/invokable-primary', ReusedRouteController::class);
    LaravelRoute::post('api/reused/invokable-secondary', ReusedRouteController::class);

    $document = (new Api())
        ->name('reused-controllers')
        ->prefix('api')
        ->withoutBroadcasting()
        ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'))
        ->toOpenAPI()
        ->build();

    expect($document['paths']['/reused/invokable-primary']['get']['summary'])->toBe('Document reused invokable route')
        ->and($document['paths']['/reused/invokable-secondary']['post']['summary'])->toBe('Document reused invokable route');
});

test('it documents all routes that reuse a controller method action', function () {
    LaravelRoute::get('api/reused/method-primary', [ReusedRouteController::class, 'show']);
    LaravelRoute::put('api/reused/method-secondary', [ReusedRouteController::class, 'show']);

    $document = (new Api())
        ->name('reused-method-controllers')
        ->prefix('api')
        ->withoutBroadcasting()
        ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'))
        ->toOpenAPI()
        ->build();

    expect($document['paths']['/reused/method-primary']['get']['summary'])->toBe('Document reused method route')
        ->and($document['paths']['/reused/method-secondary']['put']['summary'])->toBe('Document reused method route');
});

test('it applies method filters to each reused controller route independently', function () {
    LaravelRoute::get('api/reused/method-filter/get', ReusedRouteController::class);
    LaravelRoute::post('api/reused/method-filter/post', ReusedRouteController::class);

    $document = (new Api())
        ->name('reused-filtered-controllers')
        ->prefix('api')
        ->withoutBroadcasting()
        ->filterPath('^/reused/method-filter/')
        ->filterMethod('POST')
        ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'))
        ->toOpenAPI()
        ->build();

    expect(array_keys($document['paths']))->toBe(['/reused/method-filter/post'])
        ->and(array_keys($document['paths']['/reused/method-filter/post']))->toBe(['post']);
});
