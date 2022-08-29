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

/**
 * The exception to be thrown if a content stream does not exists yet but is expected to
 *
 * @api because exception is thrown during invariant checks on command execution
 */
final class ContentStreamDoesNotExistYet extends \DomainException
{
}
