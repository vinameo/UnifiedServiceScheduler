<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class SharedCacheStoreTest extends TestCase
{
    public function test_R_00_1_cache_store_with_env_example_is_database(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));
        $this->assertNotFalse($envExample);
        $this->assertMatchesRegularExpression('/^CACHE_STORE=database$/m', $envExample);

        $origEnv = $_ENV['CACHE_STORE'] ?? null;
        $origServer = $_SERVER['CACHE_STORE'] ?? null;

        try {
            $_ENV['CACHE_STORE'] = 'database';
            $_SERVER['CACHE_STORE'] = 'database';
            putenv('CACHE_STORE=database');

            $cacheConfig = require config_path('cache.php');
            $this->assertSame('database', $cacheConfig['default']);
        } finally {
            if ($origEnv !== null) {
                $_ENV['CACHE_STORE'] = $origEnv;
                putenv("CACHE_STORE={$origEnv}");
            } else {
                unset($_ENV['CACHE_STORE']);
            }
            if ($origServer !== null) {
                $_SERVER['CACHE_STORE'] = $origServer;
            } else {
                unset($_SERVER['CACHE_STORE']);
            }
        }
    }
}
