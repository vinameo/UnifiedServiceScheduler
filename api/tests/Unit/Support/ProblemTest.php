<?php

namespace Tests\Unit\Support;

use App\Support\Problem\Problem;
use PHPUnit\Framework\TestCase;

class ProblemTest extends TestCase
{
    public function test_body_has_rfc_9457_members_and_drops_empty_ones(): void
    {
        $problem = new Problem(412, 'APPOINTMENT_MODIFIED', 'Changed by someone else.');

        $this->assertSame([
            'type' => '/problems/appointment-modified',
            'title' => 'Changed by someone else.',
            'status' => 412,
            'code' => 'APPOINTMENT_MODIFIED',
            'correlationId' => 'c-123',
        ], $problem->toArray('c-123'));
    }
}
