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

namespace Neos\ContentRepository\Core\Feature\NodeTypeChange;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\EventStore\Events;
use Neos\ContentRepository\Core\EventStore\EventsToPublish;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamIdentifier;
use Neos\ContentRepository\Core\SharedModel\Node\NodePath;
use Neos\ContentRepository\Core\SharedModel\NodeType\NodeType;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIdentifier;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePointSet;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeConstraintException;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeTypeNotFoundException;
use Neos\ContentRepository\Core\Feature\ContentStreamEventStreamName;
use Neos\ContentRepository\Core\Feature\NodeTypeChange\Command\ChangeNodeAggregateType;
use Neos\ContentRepository\Core\Feature\NodeTypeChange\Event\NodeAggregateTypeWasChanged;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregatesTypeIsAmbiguous;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeTypeNotFound;
use Neos\ContentRepository\Core\Feature\Common\NodeAggregateEventPublisher;
use Neos\ContentRepository\Core\Feature\Common\NodeAggregateIdentifiersByNodePaths;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\User\UserIdentifier;
use Neos\EventStore\Model\EventStream\ExpectedVersion;

/** @codingStandardsIgnoreStart */
use Neos\ContentRepository\Core\Feature\NodeTypeChange\Command\NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy;
/** @codingStandardsIgnoreEnd */

/**
 * @internal implementation detail of Command Handlers
 */
trait NodeTypeChange
{
    abstract protected function requireProjectedNodeAggregate(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        ContentRepository $contentRepository
    ): NodeAggregate;

    abstract protected function requireConstraintsImposedByAncestorsAreMet(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeType $nodeType,
        ?NodeName $nodeName,
        array $parentNodeAggregateIdentifiers,
        ContentRepository $contentRepository
    ): void;

    abstract protected function requireNodeTypeConstraintsImposedByParentToBeMet(
        NodeType $parentsNodeType,
        ?NodeName $nodeName,
        NodeType $nodeType
    ): void;

    abstract protected function areNodeTypeConstraintsImposedByParentValid(
        NodeType $parentsNodeType,
        ?NodeName $nodeName,
        NodeType $nodeType
    ): bool;

    abstract protected function requireNodeTypeConstraintsImposedByGrandparentToBeMet(
        NodeType $grandParentsNodeType,
        ?NodeName $parentNodeName,
        NodeType $nodeType
    ): void;

    abstract protected function areNodeTypeConstraintsImposedByGrandparentValid(
        NodeType $grandParentsNodeType,
        ?NodeName $parentNodeName,
        NodeType $nodeType
    ): bool;

    abstract protected static function populateNodeAggregateIdentifiers(
        NodeType $nodeType,
        NodeAggregateIdentifiersByNodePaths $nodeAggregateIdentifiers,
        NodePath $childPath = null
    ): NodeAggregateIdentifiersByNodePaths;

    abstract protected function createEventsForMissingTetheredNode(
        NodeAggregate $parentNodeAggregate,
        Node $parentNode,
        NodeName $tetheredNodeName,
        NodeAggregateIdentifier $tetheredNodeAggregateIdentifier,
        NodeType $expectedTetheredNodeType,
        UserIdentifier $initiatingUserIdentifier,
        ContentRepository $contentRepository
    ): Events;

    /**
     * @throws NodeTypeNotFound
     * @throws NodeConstraintException
     * @throws NodeTypeNotFoundException
     * @throws NodeAggregatesTypeIsAmbiguous
     */
    private function handleChangeNodeAggregateType(
        ChangeNodeAggregateType $command,
        ContentRepository $contentRepository
    ): EventsToPublish {
        /**************
         * Constraint checks
         **************/
        // existence of content stream, node type and node aggregate
        $this->requireContentStreamToExist($command->contentStreamIdentifier, $contentRepository);
        $newNodeType = $this->requireNodeType($command->newNodeTypeName);
        $nodeAggregate = $this->requireProjectedNodeAggregate(
            $command->contentStreamIdentifier,
            $command->nodeAggregateIdentifier,
            $contentRepository
        );

        // node type detail checks
        $this->requireNodeTypeToNotBeOfTypeRoot($newNodeType);
        $this->requireTetheredDescendantNodeTypesToExist($newNodeType);
        $this->requireTetheredDescendantNodeTypesToNotBeOfTypeRoot($newNodeType);

        // the new node type must be allowed at this position in the tree
        $parentNodeAggregates = $contentRepository->getContentGraph()->findParentNodeAggregates(
            $nodeAggregate->contentStreamIdentifier,
            $nodeAggregate->nodeAggregateIdentifier
        );
        foreach ($parentNodeAggregates as $parentNodeAggregate) {
            $this->requireConstraintsImposedByAncestorsAreMet(
                $command->contentStreamIdentifier,
                $newNodeType,
                $nodeAggregate->nodeName,
                [$parentNodeAggregate->nodeAggregateIdentifier],
                $contentRepository
            );
        }

        /** @codingStandardsIgnoreStart */
        match ($command->strategy) {
            NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy::STRATEGY_HAPPY_PATH
                => $this->requireConstraintsImposedByHappyPathStrategyAreMet($nodeAggregate, $newNodeType, $contentRepository),
            NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy::STRATEGY_DELETE => null
        };
        /** @codingStandardsIgnoreStop */

        /**************
         * Preparation - make the command fully deterministic in case of rebase
         **************/
        $descendantNodeAggregateIdentifiers = static::populateNodeAggregateIdentifiers(
            $newNodeType,
            $command->tetheredDescendantNodeAggregateIdentifiers
        );
        // Write the auto-created descendant node aggregate identifiers back to the command;
        // so that when rebasing the command, it stays fully deterministic.
        $command = $command->withTetheredDescendantNodeAggregateIdentifiers($descendantNodeAggregateIdentifiers);

        /**************
         * Creating the events
         **************/
        $events = [
            new NodeAggregateTypeWasChanged(
                $command->contentStreamIdentifier,
                $command->nodeAggregateIdentifier,
                $command->newNodeTypeName
            ),
        ];

        // remove disallowed nodes
        if ($command->strategy === NodeAggregateTypeChangeChildConstraintConflictResolutionStrategy::STRATEGY_DELETE) {
            array_push($events, ...iterator_to_array($this->deleteDisallowedNodesWhenChangingNodeType(
                $nodeAggregate,
                $newNodeType,
                $command->initiatingUserIdentifier,
                $contentRepository
            )));
            array_push($events, ...iterator_to_array($this->deleteObsoleteTetheredNodesWhenChangingNodeType(
                $nodeAggregate,
                $newNodeType,
                $command->initiatingUserIdentifier,
                $contentRepository
            )));
        }

        // new tethered child nodes
        $expectedTetheredNodes = $newNodeType->getAutoCreatedChildNodes();
        foreach ($nodeAggregate->getNodes() as $node) {
            assert($node instanceof Node);
            foreach ($expectedTetheredNodes as $serializedTetheredNodeName => $expectedTetheredNodeType) {
                $tetheredNodeName = NodeName::fromString($serializedTetheredNodeName);

                $subgraph = $contentRepository->getContentGraph()->getSubgraph(
                    $node->subgraphIdentity->contentStreamIdentifier,
                    $node->originDimensionSpacePoint->toDimensionSpacePoint(),
                    VisibilityConstraints::withoutRestrictions()
                );
                $tetheredNode = $subgraph->findChildNodeConnectedThroughEdgeName(
                    $node->nodeAggregateIdentifier,
                    $tetheredNodeName
                );
                if ($tetheredNode === null) {
                    $tetheredNodeAggregateIdentifier = $command->tetheredDescendantNodeAggregateIdentifiers
                        ?->getNodeAggregateIdentifier(NodePath::fromString((string)$tetheredNodeName))
                        ?: NodeAggregateIdentifier::create();
                    array_push($events, ...iterator_to_array($this->createEventsForMissingTetheredNode(
                        $nodeAggregate,
                        $node,
                        $tetheredNodeName,
                        $tetheredNodeAggregateIdentifier,
                        $expectedTetheredNodeType,
                        $command->initiatingUserIdentifier,
                        $contentRepository
                    )));
                }
            }
        }

        return new EventsToPublish(
            ContentStreamEventStreamName::fromContentStreamIdentifier(
                $command->contentStreamIdentifier
            )->getEventStreamName(),
            NodeAggregateEventPublisher::enrichWithCommand(
                $command,
                Events::fromArray($events),
            ),
            ExpectedVersion::ANY()
        );
    }


    /**
     * NOTE: when changing this method, {@see NodeTypeChange::deleteDisallowedNodesWhenChangingNodeType}
     * needs to be modified as well (as they are structurally the same)
     * @throws NodeConstraintException|NodeTypeNotFoundException
     */
    private function requireConstraintsImposedByHappyPathStrategyAreMet(
        NodeAggregate $nodeAggregate,
        NodeType $newNodeType,
        ContentRepository $contentRepository
    ): void {
        // if we have children, we need to check whether they are still allowed
        // after we changed the node type of the $nodeAggregate to $newNodeType.
        $childNodeAggregates = $contentRepository->getContentGraph()->findChildNodeAggregates(
            $nodeAggregate->contentStreamIdentifier,
            $nodeAggregate->nodeAggregateIdentifier
        );
        foreach ($childNodeAggregates as $childNodeAggregate) {
            /* @var $childNodeAggregate NodeAggregate */
            // the "parent" of the $childNode is $node;
            // so we use $newNodeType (the target node type of $node after the operation) here.
            $this->requireNodeTypeConstraintsImposedByParentToBeMet(
                $newNodeType,
                $childNodeAggregate->nodeName,
                $this->requireNodeType($childNodeAggregate->nodeTypeName)
            );

            // we do not need to test for grandparents here, as we did not modify the grandparents.
            // Thus, if it was allowed before, it is allowed now.

            // additionally, we need to look one level down to the grandchildren as well
            // - as it could happen that these are affected by our constraint checks as well.
            $grandchildNodeAggregates = $contentRepository->getContentGraph()->findChildNodeAggregates(
                $childNodeAggregate->contentStreamIdentifier,
                $childNodeAggregate->nodeAggregateIdentifier
            );
            foreach ($grandchildNodeAggregates as $grandchildNodeAggregate) {
                /* @var $grandchildNodeAggregate NodeAggregate */
                // we do not need to test for the parent of grandchild (=child),
                // as we do not change the child's node type.
                // we however need to check for the grandparent node type.
                $this->requireNodeTypeConstraintsImposedByGrandparentToBeMet(
                    $newNodeType, // the grandparent node type changes
                    $childNodeAggregate->nodeName,
                    $this->requireNodeType($grandchildNodeAggregate->nodeTypeName)
                );
            }
        }
    }

    /**
     * NOTE: when changing this method, {@see NodeTypeChange::requireConstraintsImposedByHappyPathStrategyAreMet}
     * needs to be modified as well (as they are structurally the same)
     */
    private function deleteDisallowedNodesWhenChangingNodeType(
        NodeAggregate $nodeAggregate,
        NodeType $newNodeType,
        UserIdentifier $initiatingUserIdentifier,
        ContentRepository $contentRepository
    ): Events {
        $events = [];
        // if we have children, we need to check whether they are still allowed
        // after we changed the node type of the $nodeAggregate to $newNodeType.
        $childNodeAggregates = $contentRepository->getContentGraph()->findChildNodeAggregates(
            $nodeAggregate->contentStreamIdentifier,
            $nodeAggregate->nodeAggregateIdentifier
        );
        foreach ($childNodeAggregates as $childNodeAggregate) {
            /* @var $childNodeAggregate NodeAggregate */
            // the "parent" of the $childNode is $node; so we use $newNodeType
            // (the target node type of $node after the operation) here.
            if (
                !$childNodeAggregate->classification->isTethered()
                && !$this->areNodeTypeConstraintsImposedByParentValid(
                    $newNodeType,
                    $childNodeAggregate->nodeName,
                    $this->requireNodeType($childNodeAggregate->nodeTypeName)
                )
            ) {
                // this aggregate (or parts thereof) are DISALLOWED according to constraints.
                // We now need to find out which edges we need to remove,
                $dimensionSpacePointsToBeRemoved = $this->findDimensionSpacePointsConnectingParentAndChildAggregate(
                    $nodeAggregate,
                    $childNodeAggregate,
                    $contentRepository
                );
                // AND REMOVE THEM
                $events[] = $this->removeNodeInDimensionSpacePointSet(
                    $childNodeAggregate,
                    $dimensionSpacePointsToBeRemoved,
                    $initiatingUserIdentifier
                );
            }

            // we do not need to test for grandparents here, as we did not modify the grandparents.
            // Thus, if it was allowed before, it is allowed now.

            // additionally, we need to look one level down to the grandchildren as well
            // - as it could happen that these are affected by our constraint checks as well.
            $grandchildNodeAggregates = $contentRepository->getContentGraph()->findChildNodeAggregates(
                $childNodeAggregate->contentStreamIdentifier,
                $childNodeAggregate->nodeAggregateIdentifier
            );
            foreach ($grandchildNodeAggregates as $grandchildNodeAggregate) {
                /* @var $grandchildNodeAggregate NodeAggregate */
                // we do not need to test for the parent of grandchild (=child),
                // as we do not change the child's node type.
                // we however need to check for the grandparent node type.
                if (
                    $childNodeAggregate->nodeName !== null
                    && !$this->areNodeTypeConstraintsImposedByGrandparentValid(
                        $newNodeType, // the grandparent node type changes
                        $childNodeAggregate->nodeName,
                        $this->requireNodeType($grandchildNodeAggregate->nodeTypeName)
                    )
                ) {
                    // this aggregate (or parts thereof) are DISALLOWED according to constraints.
                    // We now need to find out which edges we need to remove,
                    $dimensionSpacePointsToBeRemoved = $this->findDimensionSpacePointsConnectingParentAndChildAggregate(
                        $childNodeAggregate,
                        $grandchildNodeAggregate,
                        $contentRepository
                    );
                    // AND REMOVE THEM
                    $events[] = $this->removeNodeInDimensionSpacePointSet(
                        $grandchildNodeAggregate,
                        $dimensionSpacePointsToBeRemoved,
                        $initiatingUserIdentifier
                    );
                }
            }
        }

        return Events::fromArray($events);
    }

    private function deleteObsoleteTetheredNodesWhenChangingNodeType(
        NodeAggregate $nodeAggregate,
        NodeType $newNodeType,
        UserIdentifier $initiatingUserIdentifier,
        ContentRepository $contentRepository
    ): Events {
        $expectedTetheredNodes = $newNodeType->getAutoCreatedChildNodes();

        $events = [];
        // find disallowed tethered nodes
        $tetheredNodeAggregates = $contentRepository->getContentGraph()->findTetheredChildNodeAggregates(
            $nodeAggregate->contentStreamIdentifier,
            $nodeAggregate->nodeAggregateIdentifier
        );

        foreach ($tetheredNodeAggregates as $tetheredNodeAggregate) {
            /* @var $tetheredNodeAggregate NodeAggregate */
            if (!isset($expectedTetheredNodes[(string)$tetheredNodeAggregate->nodeName])) {
                // this aggregate (or parts thereof) are DISALLOWED according to constraints.
                // We now need to find out which edges we need to remove,
                $dimensionSpacePointsToBeRemoved = $this->findDimensionSpacePointsConnectingParentAndChildAggregate(
                    $nodeAggregate,
                    $tetheredNodeAggregate,
                    $contentRepository
                );
                // AND REMOVE THEM
                $events[] = $this->removeNodeInDimensionSpacePointSet(
                    $tetheredNodeAggregate,
                    $dimensionSpacePointsToBeRemoved,
                    $initiatingUserIdentifier
                );
            }
        }

        return Events::fromArray($events);
    }

    /**
     * Find all dimension space points which connect two Node Aggregates.
     *
     * After we found wrong node type constraints between two aggregates, we need to remove exactly the edges where the
     * aggregates are connected as parent and child.
     *
     * Example: In this case, we want to find exactly the bold edge between PAR1 and A.
     *
     *          ╔══════╗ <------ $parentNodeAggregate (PAR1)
     * ┌──────┐ ║  PAR1║   ┌──────┐
     * │ PAR3 │ ╚══════╝   │ PAR2 │
     * └──────┘    ║       └──────┘
     *        ╲    ║          ╱
     *         ╲   ║         ╱
     *          ▼──▼──┐ ┌───▼─┐
     *          │  A  │ │  A' │ <------ $childNodeAggregate (A+A')
     *          └─────┘ └─────┘
     *
     * How do we do this?
     * - we iterate over each covered dimension space point of the full aggregate
     * - in each dimension space point, we check whether the parent node is "our" $nodeAggregate (where
     *   we originated from)
     */
    private function findDimensionSpacePointsConnectingParentAndChildAggregate(
        NodeAggregate $parentNodeAggregate,
        NodeAggregate $childNodeAggregate,
        ContentRepository $contentRepository
    ): DimensionSpacePointSet {
        $points = [];
        foreach ($childNodeAggregate->coveredDimensionSpacePoints as $coveredDimensionSpacePoint) {
            $subgraph = $contentRepository->getContentGraph()->getSubgraph(
                $childNodeAggregate->contentStreamIdentifier,
                $coveredDimensionSpacePoint,
                VisibilityConstraints::withoutRestrictions()
            );
            $parentNode = $subgraph->findParentNode($childNodeAggregate->nodeAggregateIdentifier);
            if (
                $parentNode
                && $parentNode->nodeAggregateIdentifier->equals($parentNodeAggregate->nodeAggregateIdentifier)
            ) {
                $points[] = $coveredDimensionSpacePoint;
            }
        }

        return new DimensionSpacePointSet($points);
    }

    private function removeNodeInDimensionSpacePointSet(
        NodeAggregate $nodeAggregate,
        DimensionSpacePointSet $coveredDimensionSpacePointsToBeRemoved,
        UserIdentifier $initiatingUserIdentifier
    ): NodeAggregateWasRemoved {
        return new NodeAggregateWasRemoved(
            $nodeAggregate->contentStreamIdentifier,
            $nodeAggregate->nodeAggregateIdentifier,
            // TODO: we also use the covered dimension space points as OCCUPIED dimension space points
            // - however the OCCUPIED dimension space points are not really used by now
            // (except for the change projector, which needs love anyways...)
            OriginDimensionSpacePointSet::fromDimensionSpacePointSet(
                $coveredDimensionSpacePointsToBeRemoved
            ),
            $coveredDimensionSpacePointsToBeRemoved,
            $initiatingUserIdentifier
        );
    }
}
