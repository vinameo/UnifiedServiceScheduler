<?php

namespace Tests\Unit\Notifications;

use App\Models\OutboxMessage;
use PHPUnit\Framework\TestCase;

class DisplayStatusTest extends TestCase
{
    /**
     * TC-11-15: D-11.9
     * Given rows with (PENDING, 0), (PENDING, 2), FAILED, SENT, SKIPPED;
     * When displayStatus;
     * Then Pending, Retrying, Failed, Sent, Skipped.
     */
    public function test_display_status_matches_specification(): void
    {
        $pending0 = new OutboxMessage(['status' => 'PENDING', 'attempts' => 0]);
        $this->assertSame('Pending', $pending0->displayStatus());

        $pending2 = new OutboxMessage(['status' => 'PENDING', 'attempts' => 2]);
        $this->assertSame('Retrying', $pending2->displayStatus());

        $failed = new OutboxMessage(['status' => 'FAILED', 'attempts' => 5]);
        $this->assertSame('Failed', $failed->displayStatus());

        $sent = new OutboxMessage(['status' => 'SENT', 'attempts' => 1]);
        $this->assertSame('Sent', $sent->displayStatus());

        $skipped = new OutboxMessage(['status' => 'SKIPPED', 'attempts' => 0]);
        $this->assertSame('Skipped', $skipped->displayStatus());
    }
}
