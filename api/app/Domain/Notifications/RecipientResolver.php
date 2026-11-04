<?php

namespace App\Domain\Notifications;

use App\Domain\Auth\Repositories\CustomerRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Models\OutboxMessage;

class RecipientResolver implements RecipientResolverInterface
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AppointmentRepositoryInterface $appointmentRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    public function resolve(OutboxMessage $message): ?Recipient
    {
        $customerId = $this->determineCustomerId($message);
        if ($customerId === null) {
            return null;
        }

        $customer = $this->customerRepository->find($customerId);
        if ($customer === null) {
            return null;
        }

        $user = $customer->relationLoaded('user')
            ? $customer->user
            : ($customer->user_id ? $this->userRepository->findById((int) $customer->user_id) : null);

        $email = ($user !== null && ! empty($user->email)) ? (string) $user->email : null;
        $phone = ! empty($customer->phone) ? (string) $customer->phone : null;
        $name = ! empty($customer->full_name) ? (string) $customer->full_name : null;

        if ($email === null && $phone === null) {
            return null;
        }

        return new Recipient(
            phone: $phone,
            email: $email,
            name: $name,
            customerId: (string) $customer->id,
            userId: $customer->user_id ? (int) $customer->user_id : null,
        );
    }

    private function determineCustomerId(OutboxMessage $message): ?string
    {
        $payload = $message->payload;
        if (! empty($payload['customerId'])) {
            return (string) $payload['customerId'];
        }

        $appointmentId = $message->appointment_id ?? ($payload['appointmentId'] ?? null);
        if ($appointmentId !== null) {
            $appointment = $this->appointmentRepository->find((string) $appointmentId);
            if ($appointment !== null && ! empty($appointment->customer_id)) {
                return (string) $appointment->customer_id;
            }
        }

        return null;
    }
}
