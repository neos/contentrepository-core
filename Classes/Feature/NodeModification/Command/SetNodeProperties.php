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

namespace Neos\ContentRepository\Core\Feature\NodeModification\Command;

use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

/**
 * Set property values for a given node.
 *
 * The property values support arbitrary types (but must match the NodeType's property types -
 * this is validated in the command handler).
 *
 * Internally, this object is converted into a {@see SetSerializedNodeProperties} command, which is
 * then processed and stored.
 *
 * @api commands are the write-API of the ContentRepository
 */
final readonly class SetNodeProperties implements CommandInterface
{
    /**
     * @param WorkspaceName $workspaceName The workspace in which the set properties operation is to be performed
     * @param NodeAggregateId $nodeAggregateId The id of the node aggregate to set the properties for
     * @param OriginDimensionSpacePoint $originDimensionSpacePoint The dimension space point the properties should be changed in
     * @param PropertyValuesToWrite $propertyValues Names and (unserialized) values of properties to set
     */
    private function __construct(
        public WorkspaceName $workspaceName,
        public NodeAggregateId $nodeAggregateId,
        public OriginDimensionSpacePoint $originDimensionSpacePoint,
        public PropertyValuesToWrite $propertyValues,
    ) {
    }

    /**
     * @param WorkspaceName $workspaceName The workspace in which the set properties operation is to be performed
     * @param NodeAggregateId $nodeAggregateId The id of the node aggregate to set the properties for
     * @param OriginDimensionSpacePoint $originDimensionSpacePoint The dimension space point the properties should be changed in
     * @param PropertyValuesToWrite $propertyValues Names and (unserialized) values of properties to set
     */
    public static function create(WorkspaceName $workspaceName, NodeAggregateId $nodeAggregateId, OriginDimensionSpacePoint $originDimensionSpacePoint, PropertyValuesToWrite $propertyValues): self
    {
        return new self($workspaceName, $nodeAggregateId, $originDimensionSpacePoint, $propertyValues);
    }
}
