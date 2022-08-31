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

namespace Neos\ContentRepository\Core\Feature\WorkspacePublication\Event;

use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamIdentifier;
use Neos\ContentRepository\Core\SharedModel\User\UserIdentifier;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\EventStore\EventInterface;

/**
 * @api events are the persistence-API of the content repository
 */
final class WorkspaceWasDiscarded implements EventInterface
{
    public function __construct(
        public readonly WorkspaceName $workspaceName,
        /**
         * The new, empty, content stream
         */
        public readonly ContentStreamIdentifier $newContentStreamIdentifier,
        /**
         * The old content stream (which contains the discarded data)
         */
        public readonly ContentStreamIdentifier $previousContentStreamIdentifier,
        public readonly UserIdentifier $initiatingUserIdentifier
    ) {
    }

    public static function fromArray(array $values): self
    {
        return new self(
            WorkspaceName::fromString($values['workspaceName']),
            ContentStreamIdentifier::fromString($values['newContentStreamIdentifier']),
            ContentStreamIdentifier::fromString($values['previousContentStreamIdentifier']),
            UserIdentifier::fromString($values['initiatingUserIdentifier'])
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'workspaceName' => $this->workspaceName,
            'newContentStreamIdentifier' => $this->newContentStreamIdentifier,
            'previousContentStreamIdentifier' => $this->previousContentStreamIdentifier,
            'initiatingUserIdentifier' => $this->initiatingUserIdentifier,
        ];
    }
}
