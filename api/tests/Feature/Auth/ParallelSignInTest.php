<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Concurrency\Parallel;
use Tests\TestCase;

class ParallelSignInTest extends TestCase
{
    use DatabaseTruncation;

    public function test_INV_00_5_parallel_attempts_cannot_bypass_the_lock(): void
    {
        $email = 'parallel@customer.test';
        $throttleKey = 'sign_in:'.hash('sha256', $email);

        RateLimiter::clear($throttleKey);
        DB::table('cache')->truncate();

        User::factory()->customer()->create([
            'email' => $email,
            'password' => 'correctPassword123',
        ]);

        $payload = json_encode([
            'email' => $email,
            'password' => 'wrongPassword123',
        ], JSON_THROW_ON_ERROR);

        $results = Parallel::run(10, function (int $i, float $targetStartTime) use ($payload) {
            $artisan = base_path('artisan');
            $body = escapeshellarg($payload);

            return "DB_DATABASE=scheduler_testing CACHE_STORE=database php {$artisan} testing:request /api/v1/auth/customer/sign-in --method=POST --body={$body} --wait-until={$targetStartTime}";
        }, delaySeconds: 1.5);

        $this->assertCount(10, $results);

        $statuses = [
            401 => 0,
            429 => 0,
        ];
        $invalidCredentialsLogCount = 0;

        foreach ($results as $result) {
            $code = $result['status'];
            if (isset($statuses[$code])) {
                $statuses[$code]++;
            }
            if (str_contains($result['raw'], '"outcome":"INVALID_CREDENTIALS"')) {
                $invalidCredentialsLogCount++;
            }
        }

        // At most 5 attempts may proceed to check the password (401), the rest must be locked (429)
        $this->assertLessThanOrEqual(5, $statuses[401]);
        $this->assertGreaterThanOrEqual(5, $statuses[429]);
        $this->assertSame(10, $statuses[401] + $statuses[429]);

        // At most 5 Hash::check calls (counted in request log lines with INVALID_CREDENTIALS)
        $this->assertLessThanOrEqual(5, $invalidCredentialsLogCount);
    }
}
