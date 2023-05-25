<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\EventStore;

use Neos\ContentRepository\Core\CommandHandler\CommandHandlerInterface;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\EventStore\EventStoreInterface;
use Neos\EventStore\Model\EventStream\ExpectedVersion;
use Neos\EventStore\Model\Event\StreamName;

/**
 * Result of {@see CommandHandlerInterface::handle()} that basically represents an {@see EventStoreInterface::commit()}
 * call but allows for intercepting and decorating events in {@see ContentRepository::handle()}
 *
 * @internal only used during event publishing (from within command handlers) - and their implementation is not API
 */
final class EventsToPublish
{
    public function __construct(
        public readonly StreamName $streamName,
        public readonly Events $events,
        public readonly ExpectedVersion $expectedVersion,
    ) {
    }

    public static function empty(): self
    {
        return new EventsToPublish(
            StreamName::fromString("empty"),
            Events::fromArray([]),
            ExpectedVersion::ANY()
        );
    }
}
