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

namespace Neos\ContentRepository\Core;

use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphInterface;
use Neos\ContentRepository\Core\Projection\ProjectionStateInterface;
use Neos\ContentRepository\Core\SharedModel\Exception\WorkspaceDoesNotExist;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStream;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreams;
use Neos\ContentRepository\Core\SharedModel\Workspace\Workspace;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Core\SharedModel\Workspace\Workspaces;

/**
 * A finder for a ContentGraph bound to contentStream / workspaceName
 *
 * The API way of accessing a ContentGraph is via ContentRepository::getContentGraph()
 *
 * @internal User land code should not use this directly.
 * @see ContentRepository::getContentGraph()
 */
final class ContentRepositoryReadModel implements ProjectionStateInterface
{
    public function __construct(
        private readonly ContentRepositoryReadModelAdapterInterface $adapter
    ) {
    }

    public function findWorkspaceByName(WorkspaceName $workspaceName): ?Workspace
    {
        return $this->adapter->findWorkspaceByName($workspaceName);
    }

    public function findWorkspaces(): Workspaces
    {
        return $this->adapter->findWorkspaces();
    }

    public function findContentStreamById(ContentStreamId $contentStreamId): ?ContentStream
    {
        return $this->adapter->findContentStreamById($contentStreamId);
    }

    public function findContentStreams(): ContentStreams
    {
        return $this->adapter->findContentStreams();
    }

    /**
     * The default way to get a content graph to operate on.
     * The currently assigned ContentStreamId for the given Workspace is resolved internally.
     *
     * @throws WorkspaceDoesNotExist if the provided workspace does not resolve to an existing content stream
     * @see ContentRepository::getContentGraph()
     */
    public function getContentGraphByWorkspaceName(WorkspaceName $workspaceName): ContentGraphInterface
    {
        $workspace = $this->findWorkspaceByName($workspaceName);
        if ($workspace === null) {
            throw WorkspaceDoesNotExist::butWasSupposedTo($workspaceName);
        }
        return $this->adapter->buildContentGraph($workspace->workspaceName, $workspace->currentContentStreamId);
    }

    /**
     * For testing we allow getting an instance set by both parameters, effectively overriding the relationship at will
     *
     * @param WorkspaceName $workspaceName
     * @param ContentStreamId $contentStreamId
     * @internal Only for testing
     */
    public function getContentGraphByWorkspaceNameAndContentStreamId(WorkspaceName $workspaceName, ContentStreamId $contentStreamId): ContentGraphInterface
    {
        return $this->adapter->buildContentGraph($workspaceName, $contentStreamId);
    }
}
