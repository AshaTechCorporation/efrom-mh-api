<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:' . base64_encode(str_repeat('a', 32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new \RuntimeException(
                'Automated tests are blocked unless they use SQLite :memory:.'
            );
        }
    }
}
