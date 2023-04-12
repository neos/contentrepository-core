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

namespace Neos\ContentRepository\Core\SharedModel\Exception;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;

/**
 * The exception to be thrown if a node aggregate does currently not cover the given dimension space point set
 * but is supposed to
 *
 * @api because exception is thrown during invariant checks on command execution
 */
final class NodeAggregateDoesCurrentlyNotCoverDimensionSpacePointSet extends \DomainException
{
    public static function butWasSupposedTo(
        NodeAggregateId $nodeAggregateId,
        DimensionSpacePointSet $expectedCoveredDimensionSpacePointSet,
        DimensionSpacePointSet $actualDimensionSpacePointSet
    ): NodeAggregateDoesCurrentlyNotCoverDimensionSpacePointSet {
        return new self(
            'Node aggregate "' . $nodeAggregateId->value . '" does not cover expected dimension space point set '
                . $expectedCoveredDimensionSpacePointSet->toJson() . ' but ' . $actualDimensionSpacePointSet->toJson() . '.',
            1571134743
        );
    }
}
