<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Events\Webhooks;

use Shakewell\MindbodyLaravel\Events\WebhookReceived;

/**
 * Event fired when a client is created
 */
class ClientCreated extends WebhookReceived
{
    /**
     * Get the client data from the event
     */
    public function getClient(): array
    {
        return $this->getEventData()['Client'] ?? [];
    }

    /**
     * Get the client ID
     */
    public function getClientId(): ?string
    {
        return $this->getClient()['Id'] ?? null;
    }

    /**
     * Get the client's full name
     */
    public function getClientName(): string
    {
        $client = $this->getClient();
        $firstName = $client['FirstName'] ?? '';
        $lastName = $client['LastName'] ?? '';
        
        return trim($firstName . ' ' . $lastName);
    }

    /**
     * Get the client's email
     */
    public function getClientEmail(): ?string
    {
        return $this->getClient()['Email'] ?? null;
    }
}