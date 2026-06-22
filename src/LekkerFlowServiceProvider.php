<?php

namespace PixelPenguin\LekkerFlow;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;

class LekkerFlowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lekkerflow.php', 'lekkerflow');

        $this->registerLogChannel($this->app['config']);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/lekkerflow.php' => $this->app->configPath('lekkerflow.php'),
            ], 'lekkerflow-config');
        }
    }

    /**
     * Register the `lekkerflow` log channel from package config so consumers
     * only need to add `lekkerflow` to their LOG_STACK. A channel the host app
     * has already defined in config/logging.php takes precedence.
     */
    protected function registerLogChannel(Repository $config): void
    {
        if ($config->has('logging.channels.lekkerflow')) {
            return;
        }

        $config->set('logging.channels.lekkerflow', [
            'driver' => 'monolog',
            'level' => $config->get('lekkerflow.level', 'error'),
            'handler' => LekkerFlowHandler::class,
            'handler_with' => [
                'url' => (string) $config->get('lekkerflow.url'),
                'token' => (string) $config->get('lekkerflow.token'),
                'environment' => $config->get('lekkerflow.environment') ?: $config->get('app.env', 'production'),
                'release' => $config->get('lekkerflow.release'),
                'timeout' => (int) $config->get('lekkerflow.timeout', 5),
            ],
        ]);
    }
}
