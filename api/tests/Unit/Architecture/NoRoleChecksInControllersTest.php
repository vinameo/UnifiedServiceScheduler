<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class NoRoleChecksInControllersTest extends TestCase
{
    public function test_no_role_checks_or_role_enum_comparisons_in_controllers(): void
    {
        $controllerDir = dirname(__DIR__, 3).'/app/Http/Controllers';
        $this->assertDirectoryExists($controllerDir);

        $directory = new RecursiveDirectoryIterator($controllerDir);
        $iterator = new RecursiveIteratorIterator($directory);
        $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);

        $violations = [];

        foreach ($phpFiles as $file) {
            $filePath = $file[0];
            $content = (string) file_get_contents($filePath);
            $lines = explode("\n", $content);

            foreach ($lines as $lineIndex => $line) {
                // INV-00.10: controllers must never compare ->role === or use Role:: directly
                if (preg_match('/->role\s*===|Role::/', $line)) {
                    $violations[] = sprintf('%s:%d: %s', basename($filePath), $lineIndex + 1, trim($line));
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Direct role checks found in controllers (INV-00.10 violation). Use Gates or Policies instead:\n".implode("\n", $violations)
        );
    }
}
