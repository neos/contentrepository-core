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

namespace Neos\ContentRepository\Core\Feature\DimensionSpaceAdjustment\Exception;

/**
 * The exception to be thrown if a dimension space point is found in the projection; thus we cannot allow a global
 * operation on it.
 *
 * @api
 */
final class DimensionSpacePointAlreadyExists extends \DomainException
{
}
