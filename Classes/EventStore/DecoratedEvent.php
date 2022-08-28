<?php

declare(strict_types=1);

namespace Neos\ContentRepository\EventStore;

use Neos\EventStore\Model\Event\EventId;
use Neos\EventStore\Model\Event\EventMetadata;

/**
 * If you want to publish an event with certain metadata, you can use DecoratedEvent
 *
 * @internal because no external entity can publish new events (only command handlers can)
 */
final class DecoratedEvent
{
    private function __construct(
        public readonly EventInterface $innerEvent,
        public readonly EventId $eventId,
        public readonly EventMetadata $eventMetadata,
    ) {
    }

    public static function withMetadata(DecoratedEvent|EventInterface $event, EventMetadata $metadata): self
    {
        $event = self::wrapWithDecoratedEventIfNecessary($event);
        return new self($event->innerEvent, $event->eventId, $metadata);
    }

    public static function withEventId(DecoratedEvent|EventInterface $event, EventId $eventId): self
    {
        $event = self::wrapWithDecoratedEventIfNecessary($event);
        return new self($event->innerEvent, $eventId, $event->eventMetadata);
    }

    public static function withCausationIdentifier(
        DecoratedEvent|EventInterface $event,
        EventId $causationIdentifier
    ): self {
        $event = self::wrapWithDecoratedEventIfNecessary($event);
        $eventMetadata = $event->eventMetadata->value;
        $eventMetadata['causationIdentifier'] = $causationIdentifier->value;

        return new self($event->innerEvent, $event->eventId, EventMetadata::fromArray($eventMetadata));
    }

    private static function wrapWithDecoratedEventIfNecessary(EventInterface|DecoratedEvent $event): DecoratedEvent
    {
        if ($event instanceof EventInterface) {
            $event = new self($event, EventId::create(), EventMetadata::none());
        }
        return $event;
    }
}
