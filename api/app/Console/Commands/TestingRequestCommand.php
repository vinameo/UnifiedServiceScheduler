<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

class TestingRequestCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'testing:request
                            {uri : The request URI}
                            {--method=GET : The HTTP method}
                            {--body= : JSON string of request body}
                            {--headers= : JSON string of headers}
                            {--wait-until= : Microtime float to wait until before dispatching}
                            {--cache-store= : Cache store driver to use}';

    /**
     * @var string
     */
    protected $description = 'Dispatches an HTTP request through the kernel for concurrency tests';

    public function handle(Kernel $kernel): int
    {
        $cacheStore = $this->option('cache-store');
        if (is_string($cacheStore) && $cacheStore !== '') {
            config(['cache.default' => $cacheStore]);
        }

        $waitUntil = $this->option('wait-until');
        if (is_numeric($waitUntil)) {
            $target = (float) $waitUntil;
            while (microtime(true) < $target) {
                usleep(100);
            }
        }

        $uri = (string) $this->argument('uri');
        $method = strtoupper((string) $this->option('method'));
        $body = (string) $this->option('body');
        $headersOption = (string) $this->option('headers');

        $headers = [];
        if ($headersOption !== '') {
            /** @var array<string, string> $decoded */
            $decoded = json_decode($headersOption, true) ?? [];
            $headers = $decoded;
        }

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        foreach ($headers as $key => $value) {
            $serverHeader = 'HTTP_'.strtoupper(str_replace('-', '_', $key));
            $server[$serverHeader] = $value;
        }

        $request = Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            $server,
            $body !== '' ? $body : null
        );

        $response = $kernel->handle($request);

        $output = [
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getContent() ?: '{}', true),
            'headers' => $response->headers->all(),
        ];

        $this->output->write(json_encode($output) ?: '{}');

        $kernel->terminate($request, $response);

        return 0;
    }
}
