<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$routes = \Illuminate\Support\Facades\Route::getRoutes();

foreach ($routes as $route) {
    if (str_starts_with($route->uri(), 'api/')) {
        echo 'URI: '.$route->uri()."\n";
        echo 'Action: '.json_encode($route->getAction())."\n";
        echo 'Controller: '.($route->getController() ? get_class($route->getController()) : 'null')."\n";
        echo "---\n";
        break;
    }
}
