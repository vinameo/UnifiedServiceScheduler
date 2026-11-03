<?php

namespace Tests\Unit\Support\Pagination;

use App\Support\Pagination\CursorPaginator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CursorPaginatorTest extends TestCase
{
    public function test_encode_and_decode_round_trip(): void
    {
        $payload = [
            'start_at' => '2026-11-02T09:00:00.000000Z',
            'id' => '0192e21b-0000-7000-8000-000000000001',
        ];

        $cursor = CursorPaginator::encode($payload);

        // JSON payload base64 begins with eyJz
        $this->assertStringStartsWith('eyJz', $cursor);

        $decoded = CursorPaginator::decode($cursor, ['start_at', 'id']);
        $this->assertSame($payload, $decoded);
    }

    public function test_encode_preserves_microsecond_precision_from_carbon(): void
    {
        $carbon = CarbonImmutable::parse('2026-11-02 09:00:00.654321+00:00');
        $id = '0192e21b-0000-7000-8000-000000000002';

        $cursor = CursorPaginator::encode([
            'start_at' => $carbon,
            'id' => $id,
        ]);

        $decoded = CursorPaginator::decode($cursor, ['start_at', 'id']);
        $this->assertSame('2026-11-02T09:00:00.654321+00:00', $decoded['start_at']);
        $this->assertSame($id, $decoded['id']);
    }

    public function test_decode_pipe_separated_fallback(): void
    {
        $startAt = '2026-11-02T09:00:00.123456Z';
        $id = '0192e21b-0000-7000-8000-000000000003';
        $pipeString = "{$startAt}|{$id}";
        $cursor = rtrim(strtr(base64_encode($pipeString), '+/', '-_'), '=');

        $decoded = CursorPaginator::decode($cursor, ['start_at', 'id']);
        $this->assertSame([
            'start_at' => $startAt,
            'id' => $id,
        ], $decoded);
    }

    public function test_decode_throws_validation_exception_on_empty_string(): void
    {
        $this->expectException(ValidationException::class);
        CursorPaginator::decode('', ['start_at', 'id']);
    }

    public function test_decode_throws_validation_exception_on_invalid_characters(): void
    {
        $this->expectException(ValidationException::class);
        CursorPaginator::decode('invalid cursor!@#$', ['start_at', 'id']);
    }

    public function test_decode_throws_validation_exception_on_malformed_base64(): void
    {
        $this->expectException(ValidationException::class);
        CursorPaginator::decode('xyz', ['start_at', 'id']);
    }

    public function test_decode_throws_validation_exception_on_cursor_from_another_endpoint(): void
    {
        $foreignCursor = CursorPaginator::encode(['outbox_id' => '12345']);

        $this->expectException(ValidationException::class);
        CursorPaginator::decode($foreignCursor, ['start_at', 'id']);
    }

    public function test_decode_throws_validation_exception_on_empty_field_values(): void
    {
        $emptyFieldCursor = CursorPaginator::encode([
            'start_at' => '',
            'id' => '0192e21b-0000-7000-8000-000000000004',
        ]);

        $this->expectException(ValidationException::class);
        CursorPaginator::decode($emptyFieldCursor, ['start_at', 'id']);
    }
}
