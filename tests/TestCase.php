<?php

namespace PixelPenguin\LekkerFlow\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use PixelPenguin\LekkerFlow\LekkerFlowServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LekkerFlowServiceProvider::class];
    }
}
