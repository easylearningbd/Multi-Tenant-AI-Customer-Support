<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app->make('config');
        $connection = (string) $config->get('database.default');
        $database = (string) $config->get("database.connections.{$connection}.database");

        if (! $app->environment('testing') || $connection !== 'sqlite' || $database !== ':memory:') {
            throw new LogicException(
                'Automated tests are restricted to the in-memory SQLite database. '
                .'Check PHPUnit environment and Laravel cache configuration before running tests.'
            );
        }

        return $app;
    }
}
