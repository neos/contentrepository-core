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

namespace Neos\ContentRepository\Core\Feature\NodeCreation\Command;

use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Dto\NodeAggregateIdsByNodePaths;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;

/**
 * CreateNodeAggregateWithNode
 *
 * Creates a new node aggregate with a new node in the given `contentStreamId`
 * with the given `nodeAggregateId` and `originDimensionSpacePoint`.
 * The node will be appended as child node of the given `parentNodeId` which must cover the given
 * `originDimensionSpacePoint`.
 *
 * @api commands are the write-API of the ContentRepository
 */
final class CreateNodeAggregateWithNode implements CommandInterface
{
    /**
     * @param OriginDimensionSpacePoint $originDimensionSpacePoint Origin of the new node in the dimension space. Will also be used to calculate a set of dimension points where the new node will cover from the configured specializations.
     * @param PropertyValuesToWrite $initialPropertyValues The node's initial property values. Will be merged over the node type's default property values
     * @param NodeAggregateIdsByNodePaths $tetheredDescendantNodeAggregateIds Predefined aggregate ids of tethered child nodes per path. For any tethered node that has no matching entry in this set, the node aggregate id is generated randomly. Since tethered nodes may have tethered child nodes themselves, this works for multiple levels
     * @param NodeAggregateId|null $succeedingSiblingNodeAggregateId Node aggregate id of the node's succeeding sibling (optional). If not given, the node will be added as the parent's first child
     * @param NodeName|null $nodeName The node's optional name. Set if there is a meaningful relation to its parent that should be named.
     */
    private function __construct(
        public readonly ContentStreamId $contentStreamId,
        public readonly NodeAggregateId $nodeAggregateId,
        public readonly NodeTypeName $nodeTypeName,
        public readonly OriginDimensionSpacePoint $originDimensionSpacePoint,
        public readonly NodeAggregateId $parentNodeAggregateId,
        public readonly PropertyValuesToWrite $initialPropertyValues,
        public readonly NodeAggregateIdsByNodePaths $tetheredDescendantNodeAggregateIds,
        public readonly ?NodeAggregateId $succeedingSiblingNodeAggregateId,
        public readonly ?NodeName $nodeName,
    ) {
    }

    /**
     * @param OriginDimensionSpacePoint $originDimensionSpacePoint Origin of the new node in the dimension space. Will also be used to calculate a set of dimension points where the new node will cover from the configured specializations.
     * @param NodeAggregateId|null $succeedingSiblingNodeAggregateId Node aggregate id of the node's succeeding sibling (optional). If not given, the node will be added as the parent's first child
     * @param NodeName|null $nodeName The node's optional name. Set if there is a meaningful relation to its parent that should be named.
     * @param PropertyValuesToWrite|null $initialPropertyValues The node's initial property values. Will be merged over the node type's default property values
     */
    public static function create(ContentStreamId $contentStreamId, NodeAggregateId $nodeAggregateId, NodeTypeName $nodeTypeName, OriginDimensionSpacePoint $originDimensionSpacePoint, NodeAggregateId $parentNodeAggregateId, ?NodeAggregateId $succeedingSiblingNodeAggregateId = null, ?NodeName $nodeName = null, ?PropertyValuesToWrite $initialPropertyValues = null) {
        return new self($contentStreamId, $nodeAggregateId, $nodeTypeName, $originDimensionSpacePoint, $parentNodeAggregateId, $initialPropertyValues ?: PropertyValuesToWrite::createEmpty(), NodeAggregateIdsByNodePaths::createEmpty(), $succeedingSiblingNodeAggregateId, $nodeName);
    }

    public function withInitialPropertyValues(PropertyValuesToWrite $newInitialPropertyValues): self
    {
        return new self(
            $this->contentStreamId,
            $this->nodeAggregateId,
            $this->nodeTypeName,
            $this->originDimensionSpacePoint,
            $this->parentNodeAggregateId,
            $newInitialPropertyValues,
            $this->tetheredDescendantNodeAggregateIds,
            $this->succeedingSiblingNodeAggregateId,
            $this->nodeName,
        );
    }

    public function withTetheredDescendantNodeAggregateIds(NodeAggregateIdsByNodePaths $tetheredDescendantNodeAggregateIds): self
    {
        return new self(
            $this->contentStreamId,
            $this->nodeAggregateId,
            $this->nodeTypeName,
            $this->originDimensionSpacePoint,
            $this->parentNodeAggregateId,
            $this->initialPropertyValues,
            $tetheredDescendantNodeAggregateIds,
            $this->succeedingSiblingNodeAggregateId,
            $this->nodeName,
        );
    }
}
