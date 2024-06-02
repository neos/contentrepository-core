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
final class ContentGraphAdapter implements ProjectionStateInterface
{
    /**
     * @var array<string, ContentGraphInterface> Runtime cache for {@see ContentGraphInterface} instances, indexed by their workspace name
     */
    private array $contentGraphInstancesByWorkspaceName = [];

    /**
     * @var array<string, Workspace> Runtime cache for {@see Workspace} instances, indexed by their name
     */
    private array $workspaceInstancesByName = [];

    /**
     * @var array<string, ContentStream> Runtime cache for {@see ContentStream} instances, indexed by their name
     */
    private array $contentStreamInstancesById = [];

    public function __construct(
        private readonly ContentGraphFactoryInterface $contentGraphFactory
    ) {
    }

    /**
     * To release all held instances, in case a workspace/content stream relation needs to be reset
     *
     * @internal Should only be needed after write operations (which should take care on their own)
     */
    public function forgetInstances(): void
    {
        // todo do we need to reintroduce the cache??? https://github.com/neos/neos-development-collection/pull/5246
        $this->contentGraphInstancesByWorkspaceName = [];
        $this->workspaceInstancesByName = [];
        $this->contentStreamInstancesById = [];
    }

    public function findWorkspaceByName(WorkspaceName $workspaceName): ?Workspace
    {
        if (!array_key_exists($workspaceName->value, $this->workspaceInstancesByName)) {
            $workspace = $this->contentGraphFactory->findWorkspaceByName($workspaceName);
            if ($workspace === null) {
                return null;
            }
            $this->workspaceInstancesByName[$workspaceName->value] = $workspace;
        }
        return $this->workspaceInstancesByName[$workspaceName->value];
    }

    public function getWorkspaces(): Workspaces
    {
        return $this->contentGraphFactory->getWorkspaces();
    }

    public function findContentStreamById(ContentStreamId $contentStreamId): ?ContentStream
    {
        if (!array_key_exists($contentStreamId->value, $this->contentStreamInstancesById)) {
            $contentStream = $this->contentGraphFactory->findContentStreamById($contentStreamId);
            if ($contentStream === null) {
                return null;
            }
            $this->contentStreamInstancesById[$contentStreamId->value] = $contentStream;
        }
        return $this->contentStreamInstancesById[$contentStreamId->value];
    }

    public function getContentStreams(): ContentStreams
    {
        return $this->contentGraphFactory->getContentStreams();
    }

    /**
     * @return iterable<ContentStreamId>
     * @internal This is currently only used by the {@see ContentStreamPruner} and might be removed in the future!
     */
    public function getUnusedAndRemovedContentStreamIds(): iterable
    {
        return $this->contentGraphFactory->getUnusedAndRemovedContentStreamIds();
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
        if (!array_key_exists($workspaceName->value, $this->contentGraphInstancesByWorkspaceName)) {
            $this->contentGraphInstancesByWorkspaceName[$workspaceName->value] = $this->contentGraphFactory->buildForWorkspace($workspaceName);
        }
        return $this->contentGraphInstancesByWorkspaceName[$workspaceName->value];
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
        return $this->contentGraphFactory->buildForWorkspaceAndContentStream($workspaceName, $contentStreamId);
    }
}
