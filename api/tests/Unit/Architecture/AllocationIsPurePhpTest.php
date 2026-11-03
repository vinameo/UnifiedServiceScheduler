<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AllocationIsPurePhpTest extends TestCase
{
    /**
     * TC-03-22: INV-03.1
     * When Scan app/Domain/Allocation recursive php files;
     * then No use Illuminate\Http, Illuminate\Database, Illuminate\Support\Facades, App\Models.
     */
    public function test_INV_03_1_allocation_is_pure_php(): void
    {
        $domainPath = realpath(__DIR__.'/../../../app/Domain/Allocation');
        $this->assertNotFalse($domainPath, 'Allocation domain directory must exist.');

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($domainPath)
        );

        $phpFiles = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $phpFiles[] = $file->getPathname();
            }
        }

        $this->assertNotEmpty($phpFiles, 'There must be at least one PHP file in app/Domain/Allocation.');

        $forbiddenPatterns = [
            'Illuminate\Http' => '/\buse\s+Illuminate\\\\Http\b/',
            'Illuminate\Database' => '/\buse\s+Illuminate\\\\Database\b/',
            'Illuminate\Support\Facades' => '/\buse\s+Illuminate\\\\Support\\\\Facades\b/',
            'App\Models' => '/\buse\s+App\\\\Models\b/',
        ];

        $violations = [];

        foreach ($phpFiles as $filePath) {
            $content = (string) file_get_contents($filePath);
            $relativePath = substr($filePath, (int) strlen($domainPath) - strlen('app/Domain/Allocation'));

            foreach ($forbiddenPatterns as $name => $pattern) {
                if (preg_match($pattern, $content)) {
                    $violations[] = "{$relativePath} imports {$name}";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Allocation domain must be pure PHP (INV-03.1). Violations found:\n".implode("\n", $violations)
        );
    }
}
