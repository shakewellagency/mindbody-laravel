<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Shakewell\MindbodyLaravel\Models\WebhookEvent;

/**
 * Event fired when a webhook is received from Mindbody
 */
class WebhookReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public WebhookEvent $webhookEvent;

    /**
     * Create a new event instance
     */
    public function __construct(WebhookEvent $webhookEvent)
    {
        $this->webhookEvent = $webhookEvent;
    }

    /**
     * Get the event type
     */
    public function getEventType(): string
    {
        return $this->webhookEvent->event_type;
    }

    /**
     * Get the event data
     */
    public function getEventData(): array
    {
        return $this->webhookEvent->getEventDataArray();
    }

    /**
     * Get the site ID
     */
    public function getSiteId(): ?string
    {
        return $this->webhookEvent->site_id;
    }

    /**
     * Get the Mindbody event ID
     */
    public function getEventId(): ?string
    {
        return $this->webhookEvent->event_id;
    }

    /**
     * Check if this is a specific event type
     */
    public function isEventType(string $type): bool
    {
        return $this->webhookEvent->isType($type);
    }

    /**
     * Check if this event is for a specific site
     */
    public function isForSite(string $siteId): bool
    {
        return $this->webhookEvent->isForSite($siteId);
    }
}