<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentRepository\Feature\NodeRenaming;

use Neos\ContentRepository\EventStore\Events;
use Neos\ContentRepository\EventStore\EventsToPublish;
use Neos\ContentRepository\Feature\ContentStreamEventStreamName;
use Neos\ContentRepository\Feature\NodeRenaming\Command\ChangeNodeAggregateName;
use Neos\ContentRepository\Feature\NodeRenaming\Event\NodeAggregateNameWasChanged;
use Neos\ContentRepository\Feature\Common\NodeAggregateEventPublisher;
use Neos\EventStore\Model\EventStream\ExpectedVersion;

/**
 * @internal implementation detail of Command Handlers
 */
trait NodeRenaming
{
    private function handleChangeNodeAggregateName(ChangeNodeAggregateName $command): EventsToPublish
    {

        // TODO: check if CS exists
        // TODO: check if aggregate exists and delegate to it
        // TODO: check if aggregate is root
        $events = Events::with(
            new NodeAggregateNameWasChanged(
                $command->contentStreamIdentifier,
                $command->nodeAggregateIdentifier,
                $command->newNodeName,
                $command->initiatingUserIdentifier
            ),
        );

        return new EventsToPublish(
            ContentStreamEventStreamName::fromContentStreamIdentifier(
                $command->contentStreamIdentifier
            )->getEventStreamName(),
            NodeAggregateEventPublisher::enrichWithCommand(
                $command,
                $events
            ),
            ExpectedVersion::ANY()
        );
    }
}
