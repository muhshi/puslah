<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ensure tests never accidentally boot against a non-testing database connection.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $defaultConnection = $app['config']->get('database.default');
        $databaseName = $app['config']->get("database.connections.{$defaultConnection}.database");

        if (! $app->environment('testing') || $defaultConnection !== 'sqlite' || $databaseName !== ':memory:') {
            throw new \RuntimeException(
                "Unsafe test database configuration detected (env: {$app->environment()}, {$defaultConnection}: {$databaseName}). Tests must run on sqlite :memory:."
            );
        }

        return $app;
    }
}
