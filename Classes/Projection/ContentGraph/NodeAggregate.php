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

namespace Neos\ContentRepository\Core\Projection\ContentGraph;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateClassification;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateDoesCurrentlyNotOccupyDimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePointSet;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;

/**
 * Node aggregate read model. Returned mainly from {@see ContentGraphInterface}.
 *
 * A *Node Aggregate* is the set of all nodes across different dimensions which belong to each other; i.e.
 * which represent the same "thing" (the same Page, the same Text node, the same Product).
 *
 * The system guarantees the following invariants:
 *
 * - Inside a NodeAggregate, each DimensionSpacePoint has at most one Node which covers it.
 *   To check this, the ReadableNodeAggregateInterface is used (mainly in constraint checks).
 * - The NodeType is always the same for all Nodes in a NodeAggregate
 * - all Nodes inside the NodeAggregate always have the same NodeName.
 * - all nodes inside a NodeAggregate are all of the same *classification*, which can be:
 *   - *root*: for root nodes
 *   - *tethered*: for nodes "attached" to the parent node (i.e. the old "AutoCreatedChildNodes")
 *   - *regular*: for all other nodes.
 *
 * This interface is called *Readable* because it exposes read operations on the set of nodes inside
 * a single NodeAggregate; often used for constraint checks (in command handlers).
 *
 * @api
 */
final class NodeAggregate
{
    /**
     * @param ContentStreamId $contentStreamId ID of the content stream of this node aggregate
     * @param NodeAggregateId $nodeAggregateId ID of this node aggregate
     * @param NodeAggregateClassification $classification whether this aggregate represents a root, regular or tethered node
     * @param NodeTypeName $nodeTypeName name of the node type of this aggregate
     * @param NodeName|null $nodeName optional name of this aggregate
     * @param OriginDimensionSpacePointSet $occupiedDimensionSpacePoints dimension space points this aggregate occupies
     * @param array<string,Node> $nodesByOccupiedDimensionSpacePoint
     * @param CoverageByOrigin $coverageByOccupant
     * @param DimensionSpacePointSet $coveredDimensionSpacePoints
     * @param array<string,Node> $nodesByCoveredDimensionSpacePoint
     * @param OriginByCoverage $occupationByCovered
     * @param DimensionSpacePointsBySubtreeTags $dimensionSpacePointsBySubtreeTags dimension space points for every subtree tag this aggregate is *explicitly* tagged with (excluding inherited tags)
     */
    public function __construct(
        public readonly ContentStreamId $contentStreamId,
        public readonly NodeAggregateId $nodeAggregateId,
        public readonly NodeAggregateClassification $classification,
        public readonly NodeTypeName $nodeTypeName,
        public readonly ?NodeName $nodeName,
        public readonly OriginDimensionSpacePointSet $occupiedDimensionSpacePoints,
        private readonly array $nodesByOccupiedDimensionSpacePoint,
        private readonly CoverageByOrigin $coverageByOccupant,
        public readonly DimensionSpacePointSet $coveredDimensionSpacePoints,
        private readonly array $nodesByCoveredDimensionSpacePoint,
        private readonly OriginByCoverage $occupationByCovered,
        private readonly DimensionSpacePointsBySubtreeTags $dimensionSpacePointsBySubtreeTags,
    ) {
    }

    public function occupiesDimensionSpacePoint(OriginDimensionSpacePoint $originDimensionSpacePoint): bool
    {
        return $this->occupiedDimensionSpacePoints->contains($originDimensionSpacePoint);
    }

    /**
     * Returns the nodes belonging to this aggregate, i.e. the "real materialized" node rows.
     *
     * @return iterable<int,Node>
     */
    public function getNodes(): iterable
    {
        return array_values($this->nodesByOccupiedDimensionSpacePoint);
    }

    public function getNodeByOccupiedDimensionSpacePoint(
        OriginDimensionSpacePoint $occupiedDimensionSpacePoint
    ): Node {
        if (!$this->occupiedDimensionSpacePoints->contains($occupiedDimensionSpacePoint)) {
            throw NodeAggregateDoesCurrentlyNotOccupyDimensionSpacePoint::butWasSupposedTo(
                $this->nodeAggregateId,
                $occupiedDimensionSpacePoint
            );
        }

        return $this->nodesByOccupiedDimensionSpacePoint[$occupiedDimensionSpacePoint->hash];
    }

    public function coversDimensionSpacePoint(DimensionSpacePoint $dimensionSpacePoint): bool
    {
        return $this->coveredDimensionSpacePoints->contains($dimensionSpacePoint);
    }

    public function getCoverageByOccupant(
        OriginDimensionSpacePoint $occupiedDimensionSpacePoint
    ): DimensionSpacePointSet {
        $coverage = $this->coverageByOccupant->getCoverage($occupiedDimensionSpacePoint);
        if (is_null($coverage)) {
            throw NodeAggregateDoesCurrentlyNotOccupyDimensionSpacePoint::butWasSupposedTo(
                $this->nodeAggregateId,
                $occupiedDimensionSpacePoint
            );
        }

        return $coverage;
    }

    public function getNodeByCoveredDimensionSpacePoint(DimensionSpacePoint $coveredDimensionSpacePoint): Node
    {
        if (!isset($this->coveredDimensionSpacePoints[$coveredDimensionSpacePoint->hash])) {
            throw NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint::butWasSupposedTo(
                $this->nodeAggregateId,
                $coveredDimensionSpacePoint
            );
        }

        return $this->nodesByCoveredDimensionSpacePoint[$coveredDimensionSpacePoint->hash];
    }

    public function getOccupationByCovered(DimensionSpacePoint $coveredDimensionSpacePoint): OriginDimensionSpacePoint
    {
        $occupation = $this->occupationByCovered->getOrigin($coveredDimensionSpacePoint);
        if (is_null($occupation)) {
            throw NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint::butWasSupposedTo(
                $this->nodeAggregateId,
                $coveredDimensionSpacePoint
            );
        }

        return $occupation;
    }

    /**
     * Returns the dimension space points this aggregate is *explicitly* tagged in with the specified $subtreeTag
     * NOTE: This won't respect inherited subtree tags!
     *
     * @internal This is a low level concept that is not meant to be used outside the core or tests
     */
    public function subtreeTagsDimensionSpacePoints(SubtreeTag $subtreeTag): DimensionSpacePointSet
    {
        return $this->dimensionSpacePointsBySubtreeTags->forSubtreeTag($subtreeTag);
    }
}
