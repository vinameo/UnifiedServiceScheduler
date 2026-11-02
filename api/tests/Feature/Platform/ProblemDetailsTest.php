<?php

namespace Tests\Feature\Platform;

use App\Support\Problem\ProblemException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ProblemDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/v1/__test')->group(function () {
            Route::post('/validate', fn (Request $request) => $request->validate(['name' => 'required']));
            Route::get('/auth', fn () => 'ok')->middleware('auth:sanctum');
            Route::get('/business', fn () => throw new ProblemException(
                409, 'NO_AVAILABILITY', 'No free slot.', 'Bay 2 is full.', ['alternatives' => []],
            ));
            Route::get('/crash', fn () => throw new RuntimeException('secret internals'));
        });
    }

    public function test_unknown_route_is_404_problem_json_with_correlation_id(): void
    {
        $response = $this->getJson('/api/v1/nothing-here', ['X-Correlation-Id' => 'trace-0001']);

        $response->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson([
                'type' => '/problems/not-found',
                'status' => 404,
                'code' => 'NOT_FOUND',
                'correlationId' => 'trace-0001',
            ]);
    }

    public function test_validation_error_is_400_VALIDATION_ERROR_with_field_errors(): void
    {
        $this->postJson('/api/v1/__test/validate', [])
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_missing_session_is_401_UNAUTHENTICATED(): void
    {
        $this->getJson('/api/v1/__test/auth')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_business_exception_keeps_its_code_and_extra_members(): void
    {
        $this->getJson('/api/v1/__test/business')
            ->assertConflict()
            ->assertJson([
                'code' => 'NO_AVAILABILITY',
                'title' => 'No free slot.',
                'detail' => 'Bay 2 is full.',
                'alternatives' => [],
            ]);
    }

    public function test_unexpected_error_is_500_without_internals(): void
    {
        $response = $this->getJson('/api/v1/__test/crash');

        $response->assertStatus(500)->assertJsonPath('code', 'INTERNAL_ERROR');
        $this->assertStringNotContainsString('secret internals', (string) $response->getContent());
    }
}
