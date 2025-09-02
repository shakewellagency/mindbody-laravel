<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Shakewell\MindbodyLaravel\Models\WebhookEvent;
use Carbon\Carbon;

class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        $eventTypes = [
            'appointment.booked',
            'appointment.cancelled',
            'appointment.completed',
            'client.created',
            'client.updated',
            'class.booked',
            'class.cancelled',
            'sale.completed',
            'staff.created',
            'staff.updated',
        ];

        return [
            'event_id' => 'event_' . $this->faker->uuid(),
            'event_type' => $this->faker->randomElement($eventTypes),
            'site_id' => (string) $this->faker->randomNumber(5),
            'event_data' => $this->generatePayload(),
            'headers' => null,
            'event_timestamp' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'processed' => false,
            'processed_at' => null,
            'retry_count' => 0,
            'error' => null,
            'signature' => null,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => function (array $attributes) {
                return $attributes['created_at'];
            },
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'processed' => false,
            'processed_at' => null,
            'error' => null,
        ]);
    }

    public function processed(): self
    {
        return $this->state(fn (array $attributes) => [
            'processed' => true,
            'processed_at' => $this->faker->dateTimeBetween($attributes['created_at'], 'now'),
            'error' => null,
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn (array $attributes) => [
            'processed' => false,
            'processed_at' => null,
            'error' => $this->faker->sentence(),
            'retry_count' => $this->faker->numberBetween(1, 3),
        ]);
    }

    public function appointmentBooked(): self
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'appointment.booked',
            'event_data' => $this->generateAppointmentPayload(),
        ]);
    }

    public function clientCreated(): self
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'client.created',
            'event_data' => $this->generateClientPayload(),
        ]);
    }

    public function classBooked(): self
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'class.booked',
            'event_data' => $this->generateClassPayload(),
        ]);
    }

    protected function generatePayload(): array
    {
        return [
            'EventData' => [
                'Id' => $this->faker->randomNumber(6),
                'Name' => $this->faker->words(3, true),
                'Status' => $this->faker->randomElement(['Active', 'Inactive', 'Cancelled']),
                'CreatedDateTime' => $this->faker->iso8601(),
                'UpdatedDateTime' => $this->faker->iso8601(),
            ],
        ];
    }

    protected function generateAppointmentPayload(): array
    {
        $startDateTime = $this->faker->dateTimeBetween('-1 week', '+2 weeks');
        $endDateTime = Carbon::instance($startDateTime)->addHour();

        return [
            'EventData' => [
                'Appointment' => [
                    'Id' => $this->faker->randomNumber(6),
                    'Status' => $this->faker->randomElement(['Confirmed', 'Cancelled', 'Completed']),
                    'StartDateTime' => $startDateTime->format('c'),
                    'EndDateTime' => $endDateTime->format('c'),
                    'ClientId' => 'client_' . $this->faker->randomNumber(6),
                    'StaffId' => $this->faker->randomNumber(4),
                    'LocationId' => $this->faker->randomNumber(2),
                    'SessionType' => [
                        'Id' => $this->faker->randomNumber(3),
                        'Name' => $this->faker->randomElement(['Personal Training', 'Massage', 'Consultation']),
                    ],
                ],
            ],
        ];
    }

    protected function generateClientPayload(): array
    {
        return [
            'EventData' => [
                'Client' => [
                    'Id' => 'client_' . $this->faker->randomNumber(6),
                    'FirstName' => $this->faker->firstName(),
                    'LastName' => $this->faker->lastName(),
                    'Email' => $this->faker->unique()->safeEmail(),
                    'Phone' => $this->faker->phoneNumber(),
                    'Status' => $this->faker->randomElement(['Active', 'Inactive', 'Prospect']),
                    'CreationDate' => $this->faker->iso8601(),
                    'LastModifiedDateTime' => $this->faker->iso8601(),
                ],
            ],
        ];
    }

    protected function generateClassPayload(): array
    {
        $startDateTime = $this->faker->dateTimeBetween('-1 week', '+2 weeks');
        $endDateTime = Carbon::instance($startDateTime)->addHour();

        return [
            'EventData' => [
                'Class' => [
                    'Id' => $this->faker->randomNumber(6),
                    'ClassDescription' => [
                        'Id' => $this->faker->randomNumber(4),
                        'Name' => $this->faker->randomElement(['Yoga', 'Pilates', 'Zumba', 'Spin']),
                        'Description' => $this->faker->sentence(),
                    ],
                    'StartDateTime' => $startDateTime->format('c'),
                    'EndDateTime' => $endDateTime->format('c'),
                    'Staff' => [
                        'Id' => $this->faker->randomNumber(4),
                        'FirstName' => $this->faker->firstName(),
                        'LastName' => $this->faker->lastName(),
                    ],
                    'Location' => [
                        'Id' => $this->faker->randomNumber(2),
                        'Name' => $this->faker->company() . ' Location',
                    ],
                    'MaxCapacity' => $this->faker->numberBetween(10, 50),
                    'BookedCapacity' => $this->faker->numberBetween(0, 30),
                ],
                'Client' => [
                    'Id' => 'client_' . $this->faker->randomNumber(6),
                    'FirstName' => $this->faker->firstName(),
                    'LastName' => $this->faker->lastName(),
                    'Email' => $this->faker->safeEmail(),
                ],
            ],
        ];
    }
}