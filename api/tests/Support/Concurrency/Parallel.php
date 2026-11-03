<?php

namespace Tests\Support\Concurrency;

use Closure;
use Illuminate\Support\Facades\Process;

class Parallel
{
    /**
     * Runs N parallel commands that all wait for the exact same target start time.
     *
     * @param  Closure(int $index, float $targetStartTime): string  $commandBuilder
     * @return array<int, array{status: int, body: array<string, mixed>, headers: array<string, array<int, string>>, raw: string}>
     */
    public static function run(int $count, Closure $commandBuilder, float $delaySeconds = 0.5): array
    {
        $targetStartTime = microtime(true) + $delaySeconds;

        $pool = Process::pool(function ($pool) use ($count, $commandBuilder, $targetStartTime) {
            for ($i = 0; $i < $count; $i++) {
                $cmd = $commandBuilder($i, $targetStartTime);
                $pool->command($cmd);
            }
        });

        $results = $pool->start()->wait();

        $output = [];
        foreach ($results as $result) {
            $raw = trim($result->output());
            $lines = array_filter(explode("\n", $raw));
            $lastLine = end($lines);

            $decoded = is_string($lastLine) ? json_decode($lastLine, true) : null;
            if (is_array($decoded)) {
                $output[] = [
                    'status' => (int) ($decoded['status'] ?? 0),
                    'body' => (array) ($decoded['body'] ?? []),
                    'headers' => (array) ($decoded['headers'] ?? []),
                    'raw' => $raw,
                ];
            }
        }

        return $output;
    }
}
