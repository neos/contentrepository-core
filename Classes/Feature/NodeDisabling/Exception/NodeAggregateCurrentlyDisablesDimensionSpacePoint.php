<?php

namespace Neos\ContentRepository\Core\Feature\NodeDisabling\Exception;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */


/**
 * The exception to be thrown if a node aggregate currently disables a given dimension space point
 * but wasn't expected to do
 *
 * @api
 */
final class NodeAggregateCurrentlyDisablesDimensionSpacePoint extends \DomainException
{
}
