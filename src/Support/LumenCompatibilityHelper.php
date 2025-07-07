<?php

namespace LaravelSpectrum\Support;

use Illuminate\Support\Facades\Route;

class LumenCompatibilityHelper
{
    /**
     * LaravelとLumenの差異を吸収
     */
    public static function isLumen(): bool
    {
        return ! class_exists('Illuminate\\Foundation\\Application') ||
               str_contains(app()->version(), 'Lumen');
    }

    /**
     * ルートパターンを取得
     */
    public static function getRoutePatterns(): array
    {
        if (self::isLumen()) {
            // Lumenの場合、デフォルトでAPIルートのみ
            return config('spectrum.route_patterns', ['*']);
        }

        // Laravelの場合
        return config('spectrum.route_patterns', ['api/*']);
    }

    /**
     * ルートコレクションを取得
     *
     * @return mixed
     */
    public static function getRoutes()
    {
        if (self::isLumen()) {
            // Lumenの場合
            /** @var mixed $app */
            $app = app();

            return $app->router->getRoutes();
        }

        // Laravelの場合
        return Route::getRoutes();
    }
}
