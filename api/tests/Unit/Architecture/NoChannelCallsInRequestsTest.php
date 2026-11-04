<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class NoChannelCallsInRequestsTest extends TestCase
{
    /**
     * TC-11-03: INV-11.1
     * When Scan app/Http/** and app/Domain/Booking/**;
     * then No reference to NotificationChannel or Mail::.
     */
    public function test_INV_11_1_no_channel_calls_in_requests_or_booking(): void
    {
        $baseDir = dirname(__DIR__, 3).'/app';
        $scanDirs = [
            $baseDir.'/Http',
            $baseDir.'/Domain/Booking',
        ];

        $forbiddenPatterns = [
            'NotificationChannel' => '/\bNotificationChannel\b/',
            'Mail::' => '/\bMail::/',
        ];

        $violations = [];

        foreach ($scanDirs as $dir) {
            $this->assertDirectoryExists($dir);

            $directory = new RecursiveDirectoryIterator($dir);
            $iterator = new RecursiveIteratorIterator($directory);
            $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);

            foreach ($phpFiles as $file) {
                $filePath = $file[0];
                $content = (string) file_get_contents($filePath);
                $lines = explode("\n", $content);

                foreach ($lines as $lineIndex => $line) {
                    foreach ($forbiddenPatterns as $name => $pattern) {
                        if (preg_match($pattern, $line)) {
                            $violations[] = sprintf('%s:%d references %s (%s)', $filePath, $lineIndex + 1, $name, trim($line));
                        }
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "No SMS/email/DMS channel or Mail:: calls allowed in request path or booking domain (INV-11.1). Violations found:\n".implode("\n", $violations)
        );
    }
}
