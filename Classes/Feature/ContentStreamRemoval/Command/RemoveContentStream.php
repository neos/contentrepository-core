<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Feature\ContentStreamRemoval\Command;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;

/**
 * Command to remove an existing content stream
 *
 * @api commands are the write-API of the ContentRepository
 */
final class RemoveContentStream implements CommandInterface
{
    public function __construct(
        public readonly ContentStreamId $contentStreamId,
    ) {
    }
}
