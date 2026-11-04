<?php

namespace Tests\Support\Concurrency;

use Closure;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

class Parallel
{
    /**
     * Run $n parallel requests using Process::pool.
     *
     * @param  int  $count  Number of processes
     * @param  Closure(int, float): (string|array{method?: string, uri?: string, user?: mixed, headers?: array<string, string>, body?: mixed, json?: mixed, now?: string})  $requestFor
     * @return array<int, array{status: int, body: array<string, mixed>, headers: array<string, array<int, string>>, raw: string}>
     */
    public static function run(int $count, Closure $requestFor, float $delaySeconds = 0.5): array
    {
        $targetStartTime = microtime(true) + $delaySeconds;

        $pool = Process::pool(function (Pool $pool) use ($count, $requestFor, $targetStartTime): void {
            for ($i = 0; $i < $count; $i++) {
                $item = $requestFor($i, $targetStartTime);

                if (is_string($item)) {
                    $pool->command($item);

                    continue;
                }

                $method = $item['method'] ?? 'GET';
                $uri = $item['uri'] ?? '/';
                $userId = '';
                if (isset($item['user'])) {
                    $userId = is_object($item['user']) && isset($item['user']->id)
                        ? (string) $item['user']->id
                        : (string) $item['user'];
                }

                $encodedHeaders = json_encode($item['headers'] ?? []);
                $bodyData = $item['body'] ?? ($item['json'] ?? null);
                $encodedBody = is_array($bodyData)
                    ? json_encode($bodyData)
                    : (string) ($bodyData ?? '');
                $body = is_string($encodedBody) ? $encodedBody : '';

                $artisan = base_path('artisan');
                $cmd = "APP_ENV=testing DB_DATABASE=scheduler_testing php {$artisan} testing:request "
                    .escapeshellarg($uri)
                    ." --method={$method}"
                    ." --start-at={$targetStartTime}";

                if ($userId !== '') {
                    $cmd .= ' --user='.escapeshellarg($userId);
                }
                if (is_string($encodedHeaders) && $encodedHeaders !== '[]') {
                    $cmd .= ' --headers='.escapeshellarg($encodedHeaders);
                }
                if ($body !== '') {
                    $cmd .= ' --body='.escapeshellarg($body);
                }
                if (isset($item['now'])) {
                    $cmd .= ' --now='.escapeshellarg((string) $item['now']);
                }

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
            } else {
                $output[] = [
                    'status' => $result->exitCode() === 0 ? 0 : 500,
                    'body' => ['raw' => $raw, 'error' => $result->errorOutput()],
                    'headers' => [],
                    'raw' => $raw,
                ];
            }
        }

        return $output;
    }
}
