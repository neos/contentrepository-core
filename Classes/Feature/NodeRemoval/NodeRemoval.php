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

namespace Neos\ContentRepository\Core\Feature\NodeRemoval;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace;
use Neos\ContentRepository\Core\DimensionSpace\Exception\DimensionSpacePointNotFound;
use Neos\ContentRepository\Core\EventStore\Events;
use Neos\ContentRepository\Core\EventStore\EventsToPublish;
use Neos\ContentRepository\Core\SharedModel\Exception\ContentStreamDoesNotExistYet;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregatesTypeIsAmbiguous;
use Neos\ContentRepository\Core\SharedModel\Exception\TetheredNodeAggregateCannotBeRemoved;
use Neos\ContentRepository\Core\Feature\Common\NodeAggregateEventPublisher;
use Neos\ContentRepository\Core\Feature\ContentStreamEventStreamName;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIdentifier;
use Neos\EventStore\Model\EventStream\ExpectedVersion;

/**
 * @internal implementation detail of Command Handlers
 */
trait NodeRemoval
{
    abstract protected function getInterDimensionalVariationGraph(): DimensionSpace\InterDimensionalVariationGraph;

    abstract protected function areAncestorNodeTypeConstraintChecksEnabled(): bool;

    /**
     * @param RemoveNodeAggregate $command
     * @return EventsToPublish
     * @throws NodeAggregatesTypeIsAmbiguous
     * @throws ContentStreamDoesNotExistYet
     * @throws DimensionSpacePointNotFound
     */
    private function handleRemoveNodeAggregate(
        RemoveNodeAggregate $command,
        ContentRepository $contentRepository
    ): EventsToPublish {
        $this->requireContentStreamToExist($command->contentStreamIdentifier, $contentRepository);
        $nodeAggregate = $this->requireProjectedNodeAggregate(
            $command->contentStreamIdentifier,
            $command->nodeAggregateIdentifier,
            $contentRepository
        );
        $this->requireDimensionSpacePointToExist($command->coveredDimensionSpacePoint);
        $this->requireNodeAggregateNotToBeTethered($nodeAggregate);
        $this->requireNodeAggregateToCoverDimensionSpacePoint(
            $nodeAggregate,
            $command->coveredDimensionSpacePoint
        );
        if ($command->removalAttachmentPoint instanceof NodeAggregateIdentifier) {
            $this->requireProjectedNodeAggregate(
                $command->contentStreamIdentifier,
                $command->removalAttachmentPoint,
                $contentRepository
            );
        }

        $events = Events::with(
            new NodeAggregateWasRemoved(
                $command->contentStreamIdentifier,
                $command->nodeAggregateIdentifier,
                $command->nodeVariantSelectionStrategy->resolveAffectedOriginDimensionSpacePoints(
                    $nodeAggregate->getOccupationByCovered($command->coveredDimensionSpacePoint),
                    $nodeAggregate,
                    $this->getInterDimensionalVariationGraph()
                ),
                $command->nodeVariantSelectionStrategy->resolveAffectedDimensionSpacePoints(
                    $command->coveredDimensionSpacePoint,
                    $nodeAggregate,
                    $this->getInterDimensionalVariationGraph()
                ),
                $command->initiatingUserIdentifier,
                $command->removalAttachmentPoint
            )
        );

        return new EventsToPublish(
            ContentStreamEventStreamName::fromContentStreamIdentifier($command->contentStreamIdentifier)
                ->getEventStreamName(),
            NodeAggregateEventPublisher::enrichWithCommand(
                $command,
                $events
            ),
            ExpectedVersion::ANY()
        );
    }

    protected function requireNodeAggregateNotToBeTethered(NodeAggregate $nodeAggregate): void
    {
        if ($nodeAggregate->classification->isTethered()) {
            throw new TetheredNodeAggregateCannotBeRemoved(
                'The node aggregate "' . $nodeAggregate->nodeAggregateIdentifier
                . '" is tethered, and thus cannot be removed.',
                1597753832
            );
        }
    }
}
