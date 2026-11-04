<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

class TestingRequest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'testing:request
                            {uri? : The request URI}
                            {--uri= : The request URI option}
                            {--method=GET : HTTP method}
                            {--user= : User ID to authenticate as}
                            {--body= : Request body payload}
                            {--json= : Request body payload (alias for --body)}
                            {--headers= : JSON encoded headers}
                            {--header=* : Individual header in Key:Value format}
                            {--start-at= : Unix timestamp with microsecond precision to wait for}
                            {--wait-until= : Alias for --start-at}
                            {--now= : ISO timestamp for app clock}
                            {--cache-store= : Cache store driver to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatches an HTTP request through the kernel for concurrency tests';

    public function isHidden(): bool
    {
        return ! app()->environment('testing');
    }

    public function handle(Kernel $kernel): int
    {
        if (! $this->laravel->environment('testing')) {
            $this->error('The testing:request command is only available in the testing environment.');

            return 1;
        }

        $cacheStore = $this->option('cache-store');
        if (is_string($cacheStore) && $cacheStore !== '') {
            config(['cache.default' => $cacheStore]);
        }

        $nowOption = $this->option('now');
        if ($nowOption !== null && $nowOption !== '') {
            $parsedNow = CarbonImmutable::parse((string) $nowOption);
            CarbonImmutable::setTestNow($parsedNow);
            Carbon::setTestNow($parsedNow);
        }

        $target = $this->option('start-at') ?: $this->option('wait-until');
        if ($target !== null && is_numeric($target)) {
            $targetTime = (float) $target;
            while (microtime(true) < $targetTime) {
                usleep(100);
            }
        }

        $userId = $this->option('user');
        $user = null;
        if ($userId !== null && $userId !== '') {
            $user = User::find($userId);
            if ($user !== null) {
                Auth::guard('sanctum')->setUser($user);
                Auth::guard('web')->setUser($user);
                Auth::setUser($user);
                if (class_exists(Sanctum::class)) {
                    Sanctum::actingAs($user);
                }
            }
        }

        $uriArg = $this->argument('uri');
        $uriOpt = $this->option('uri');
        $uri = (string) ($uriArg ?: ($uriOpt ?: '/'));

        $method = strtoupper((string) ($this->option('method') ?: 'GET'));
        $body = (string) ($this->option('json') ?: ($this->option('body') ?? ''));

        $headers = [];
        $rawHeaders = $this->option('headers');
        if ($rawHeaders !== null && $rawHeaders !== '') {
            $decoded = json_decode((string) $rawHeaders, true);
            if (is_array($decoded)) {
                /** @var array<string, string> $decoded */
                $headers = $decoded;
            }
        }

        /** @var list<string> $headerOptions */
        $headerOptions = (array) $this->option('header');
        foreach ($headerOptions as $headerLine) {
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        foreach ($headers as $key => $value) {
            $normalizedKey = strtoupper(str_replace('-', '_', (string) $key));
            if ($normalizedKey === 'CONTENT_TYPE') {
                $server['CONTENT_TYPE'] = (string) $value;
            } elseif ($normalizedKey === 'ACCEPT') {
                $server['HTTP_ACCEPT'] = (string) $value;
            } else {
                $server['HTTP_'.$normalizedKey] = (string) $value;
            }
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

        if ($user !== null) {
            $request->setUserResolver(fn () => $user);
        }

        $response = $kernel->handle($request);

        $content = $response->getContent();
        $decodedBody = false;
        if ($content !== false && $content !== '') {
            $decodedBody = json_decode($content, true);
        }

        $output = [
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body' => $decodedBody !== null && $decodedBody !== false ? $decodedBody : ($content ?: []),
        ];

        $this->output->write(json_encode($output) ?: '{}');

        $kernel->terminate($request, $response);

        return 0;
    }
}
