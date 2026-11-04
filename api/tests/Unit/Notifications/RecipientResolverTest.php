<?php

namespace Tests\Unit\Notifications;

use App\Domain\Auth\Repositories\CustomerRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Recipient;
use App\Domain\Notifications\RecipientResolver;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\OutboxMessage;
use App\Models\User;
use Tests\TestCase;

class RecipientResolverTest extends TestCase
{
    public function test_resolves_recipient_from_payload_customer_id(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $appointmentRepo = $this->createMock(AppointmentRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);

        $customer = new Customer;
        $customer->id = 'cust-123';
        $customer->full_name = 'Nguyễn Văn Hùng';
        $customer->phone = '+84901234567';
        $customer->user_id = 42;

        $user = new User;
        $user->id = 42;
        $user->email = 'hung@example.com';
        $customer->setRelation('user', $user);

        $customerRepo->expects($this->once())
            ->method('find')
            ->with('cust-123')
            ->willReturn($customer);

        $message = new OutboxMessage([
            'id' => 'msg-1',
            'event_id' => 'evt-1',
            'dealership_id' => 'dlr-1',
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['customerId' => 'cust-123', 'reference' => 'APT-001'],
        ]);

        $resolver = new RecipientResolver($customerRepo, $appointmentRepo, $userRepo);
        $recipient = $resolver->resolve($message);

        $this->assertInstanceOf(Recipient::class, $recipient);
        $this->assertSame('+84901234567', $recipient->phone);
        $this->assertSame('hung@example.com', $recipient->email);
        $this->assertSame('Nguyễn Văn Hùng', $recipient->name);
        $this->assertSame('cust-123', $recipient->customerId);
        $this->assertSame(42, $recipient->userId);
    }

    public function test_resolves_recipient_from_appointment_when_customer_id_not_in_payload(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $appointmentRepo = $this->createMock(AppointmentRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);

        $appointment = new Appointment;
        $appointment->id = 'apt-999';
        $appointment->customer_id = 'cust-456';

        $customer = new Customer;
        $customer->id = 'cust-456';
        $customer->full_name = 'Trần Thị Mai';
        $customer->phone = '+84909876543';
        $customer->user_id = 88;

        $user = new User;
        $user->id = 88;
        $user->email = 'mai@example.com';

        $appointmentRepo->expects($this->once())
            ->method('find')
            ->with('apt-999')
            ->willReturn($appointment);

        $customerRepo->expects($this->once())
            ->method('find')
            ->with('cust-456')
            ->willReturn($customer);

        $userRepo->expects($this->once())
            ->method('findById')
            ->with(88)
            ->willReturn($user);

        $message = new OutboxMessage([
            'id' => 'msg-2',
            'event_id' => 'evt-2',
            'dealership_id' => 'dlr-1',
            'appointment_id' => 'apt-999',
            'event_type' => OutboxEventType::AppointmentCancelled,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['status' => 'CANCELLED'],
        ]);

        $resolver = new RecipientResolver($customerRepo, $appointmentRepo, $userRepo);
        $recipient = $resolver->resolve($message);

        $this->assertInstanceOf(Recipient::class, $recipient);
        $this->assertSame('+84909876543', $recipient->phone);
        $this->assertSame('mai@example.com', $recipient->email);
        $this->assertSame('Trần Thị Mai', $recipient->name);
        $this->assertSame('cust-456', $recipient->customerId);
        $this->assertSame(88, $recipient->userId);
    }

    public function test_returns_null_when_customer_and_appointment_not_found(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $appointmentRepo = $this->createMock(AppointmentRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);

        $message = new OutboxMessage([
            'id' => 'msg-3',
            'event_id' => 'evt-3',
            'dealership_id' => 'dlr-1',
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['foo' => 'bar'],
        ]);

        $resolver = new RecipientResolver($customerRepo, $appointmentRepo, $userRepo);
        $this->assertNull($resolver->resolve($message));
    }

    public function test_returns_null_when_customer_row_is_missing(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $appointmentRepo = $this->createMock(AppointmentRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);

        $customerRepo->expects($this->once())
            ->method('find')
            ->with('non-existent-cust')
            ->willReturn(null);

        $message = new OutboxMessage([
            'id' => 'msg-4',
            'event_id' => 'evt-4',
            'dealership_id' => 'dlr-1',
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['customerId' => 'non-existent-cust'],
        ]);

        $resolver = new RecipientResolver($customerRepo, $appointmentRepo, $userRepo);
        $this->assertNull($resolver->resolve($message));
    }

    public function test_returns_null_when_customer_has_neither_phone_nor_email(): void
    {
        $customerRepo = $this->createMock(CustomerRepositoryInterface::class);
        $appointmentRepo = $this->createMock(AppointmentRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);

        $customer = new Customer;
        $customer->id = 'cust-empty';
        $customer->phone = '';
        $customer->user_id = 999;

        $customerRepo->expects($this->once())
            ->method('find')
            ->with('cust-empty')
            ->willReturn($customer);

        $userRepo->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $message = new OutboxMessage([
            'id' => 'msg-5',
            'event_id' => 'evt-5',
            'dealership_id' => 'dlr-1',
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['customerId' => 'cust-empty'],
        ]);

        $resolver = new RecipientResolver($customerRepo, $appointmentRepo, $userRepo);
        $this->assertNull($resolver->resolve($message));
    }
}
