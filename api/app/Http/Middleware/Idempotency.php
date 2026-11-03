<?php

namespace App\Http\Middleware;

use App\Domain\Booking\Events\IdempotentReplayed;
use App\Domain\Booking\Exceptions\IdempotencyKeyMismatch;
use App\Domain\Booking\Exceptions\IdempotencyKeyRequired;
use App\Domain\Booking\Exceptions\RequestInProgress;
use App\Support\Problem\ProblemRenderer;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Idempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce on state-changing methods
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            throw new IdempotencyKeyRequired;
        }

        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages([
                'Idempotency-Key' => ['The Idempotency-Key must be a valid UUID.'],
            ]);
        }

        $user = $request->user();
        $userId = $user !== null ? (int) $user->id : 0;

        $method = strtoupper($request->method());
        $path = '/'.ltrim($request->path(), '/');
        $canonicalBody = self::canonicalizeBody($request);
        $rawPayload = $method."\n".$path."\n".$canonicalBody;
        $requestHash = hash('sha256', $rawPayload);

        $now = CarbonImmutable::now();
        $ttlHours = (int) config('app.idempotency_ttl_hours', 24);
        $expiresAt = $now->addHours($ttlHours);
        $lockedUntil = $now->addSeconds(60);

        $id = (string) Str::uuid();

        $inserted = DB::table('idempotency_keys')->insertOrIgnore([
            'id' => $id,
            'user_id' => $userId,
            'key' => $key,
            'method' => $method,
            'path' => $path,
            'request_hash' => $requestHash,
            'status' => 'IN_PROGRESS',
            'locked_until' => $lockedUntil,
            'created_at' => $now,
            'expires_at' => $expiresAt,
        ]);

        if ($inserted === 1) {
            return $this->executeAndRecord($id, $key, $request, $next);
        }

        // Row was not inserted due to conflict on (user_id, key)
        $existing = DB::table('idempotency_keys')
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();

        if ($existing === null) {
            // Deleted in the meantime, try once to insert
            $insertedAgain = DB::table('idempotency_keys')->insertOrIgnore([
                'id' => $id,
                'user_id' => $userId,
                'key' => $key,
                'method' => $method,
                'path' => $path,
                'request_hash' => $requestHash,
                'status' => 'IN_PROGRESS',
                'locked_until' => $lockedUntil,
                'created_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            if ($insertedAgain === 1) {
                return $this->executeAndRecord($id, $key, $request, $next);
            }

            $existing = DB::table('idempotency_keys')
                ->where('user_id', $userId)
                ->where('key', $key)
                ->first();

            if ($existing === null) {
                throw new RequestInProgress;
            }
        }

        // 5a. expires_at <= now -> delete the old row, back to step 3 (once)
        if (CarbonImmutable::parse($existing->expires_at)->lessThanOrEqualTo($now)) {
            DB::table('idempotency_keys')->where('id', $existing->id)->delete();

            $insertedExpired = DB::table('idempotency_keys')->insertOrIgnore([
                'id' => $id,
                'user_id' => $userId,
                'key' => $key,
                'method' => $method,
                'path' => $path,
                'request_hash' => $requestHash,
                'status' => 'IN_PROGRESS',
                'locked_until' => $lockedUntil,
                'created_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            if ($insertedExpired === 1) {
                return $this->executeAndRecord($id, $key, $request, $next);
            }

            throw new RequestInProgress;
        }

        // 5b. Different request_hash -> 422 IDEMPOTENCY_KEY_MISMATCH
        if ($existing->request_hash !== $requestHash) {
            throw new IdempotencyKeyMismatch;
        }

        // 5c. COMPLETED -> return saved response + Idempotent-Replayed: true, dispatch IdempotentReplayed
        if ($existing->status === 'COMPLETED') {
            IdempotentReplayed::dispatch($userId, $key);

            /** @var array<string, string> $savedHeaders */
            $savedHeaders = json_decode((string) $existing->response_headers, true) ?? [];
            $headers = array_merge($savedHeaders, [
                'Idempotent-Replayed' => 'true',
            ]);

            return response((string) $existing->response_body, (int) $existing->response_status, $headers);
        }

        // 5d. IN_PROGRESS, locked_until > now -> 409 REQUEST_IN_PROGRESS
        $lockedUntilExisting = CarbonImmutable::parse($existing->locked_until);
        if ($lockedUntilExisting->greaterThan($now)) {
            throw new RequestInProgress;
        }

        // 5e. IN_PROGRESS, locked_until <= now -> atomic takeover
        $affected = DB::table('idempotency_keys')
            ->where('id', $existing->id)
            ->where('status', 'IN_PROGRESS')
            ->where('locked_until', '<=', $now)
            ->update([
                'locked_until' => $now->addSeconds(60),
            ]);

        if ($affected === 1) {
            return $this->executeAndRecord($existing->id, $key, $request, $next);
        }

        throw new RequestInProgress;
    }

    private function executeAndRecord(string $rowId, string $key, Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $response = ProblemRenderer::toProblem($e)->toResponse($request);
        }

        $status = $response->getStatusCode();

        if ($status >= 500) {
            DB::table('idempotency_keys')->where('id', $rowId)->delete();

            return $response;
        }

        $savedHeaders = [];
        foreach (['Location', 'ETag', 'Content-Type'] as $headerName) {
            if ($response->headers->has($headerName)) {
                $savedHeaders[$headerName] = (string) $response->headers->get($headerName);
            }
        }

        $bodyContent = (string) $response->getContent();
        if (strlen($bodyContent) > 65536) {
            Log::warning("Response body for idempotency key {$key} exceeds 64KB; deleting key without caching.");
            DB::table('idempotency_keys')->where('id', $rowId)->delete();

            return $response;
        }

        DB::table('idempotency_keys')
            ->where('id', $rowId)
            ->update([
                'status' => 'COMPLETED',
                'response_status' => $status,
                'response_headers' => json_encode($savedHeaders, JSON_THROW_ON_ERROR),
                'response_body' => $bodyContent,
            ]);

        return $response;
    }

    private static function canonicalizeBody(Request $request): string
    {
        $content = (string) $request->getContent();
        if ($content === '') {
            return '';
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return (string) json_encode(self::canonicalizeArray($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $content;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function canonicalizeArray(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::canonicalizeArray($value);
            }
        }

        return $data;
    }
}
