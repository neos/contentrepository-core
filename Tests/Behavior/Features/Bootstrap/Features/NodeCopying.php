<?php
declare(strict_types=1);
namespace Neos\ContentRepository\Core\Tests\Behavior\Features\Bootstrap\Features;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Gherkin\Node\TableNode;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphInterface;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamIdentifier;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIdentifier;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeDuplication\Command\CopyNodesRecursively;
use Neos\ContentRepository\Core\Feature\NodeDuplication\Command\NodeAggregateIdentifierMapping;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\User\UserIdentifier;
use Neos\ContentRepository\Core\Tests\Behavior\Features\Helper\ContentGraphs;
use Neos\ContentRepository\Core\Tests\Behavior\Features\Helper\NodesByAdapter;

/**
 * The node copying trait for behavioral tests
 */
trait NodeCopying
{
    abstract protected function getContentRepository(): ContentRepository;

    abstract protected function getCurrentContentStreamIdentifier(): ?ContentStreamIdentifier;

    abstract protected function getCurrentDimensionSpacePoint(): ?DimensionSpacePoint;

    abstract protected function getCurrentUserIdentifier(): ?UserIdentifier;

    abstract protected function getAvailableContentGraphs(): ContentGraphs;

    abstract protected function getCurrentNodes(): ?NodesByAdapter;

    abstract protected function readPayloadTable(TableNode $payloadTable): array;

    /**
     * @When /^the command CopyNodesRecursively is executed, copying the current node aggregate with payload:$/
     */
    public function theCommandCopyNodesRecursivelyIsExecutedCopyingTheCurrentNodeAggregateWithPayload(TableNode $payloadTable)
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        $contentGraphs = $this->getAvailableContentGraphs()->getIterator()->getArrayCopy();
        $contentGraph = reset($contentGraphs);
        assert($contentGraph instanceof ContentGraphInterface);
        $subgraph = $contentGraph->getSubgraph(
            $this->getCurrentContentStreamIdentifier(),
            $this->getCurrentDimensionSpacePoint(),
            VisibilityConstraints::withoutRestrictions()
        );
        $currentNodes = $this->getCurrentNodes()->getIterator()->getArrayCopy();
        $currentNode = reset($currentNodes);
        $targetDimensionSpacePoint = isset($commandArguments['targetDimensionSpacePoint'])
            ? OriginDimensionSpacePoint::fromArray($commandArguments['targetDimensionSpacePoint'])
            : OriginDimensionSpacePoint::fromDimensionSpacePoint($this->getCurrentDimensionSpacePoint());
        $initiatingUserIdentifier = isset($commandArguments['initiatingUserIdentifier'])
            ? UserIdentifier::fromString($commandArguments['initiatingUserIdentifier'])
            : $this->getCurrentUserIdentifier();
        $targetSucceedingSiblingNodeAggregateIdentifier = isset($commandArguments['targetSucceedingSiblingNodeAggregateIdentifier'])
            ? NodeAggregateIdentifier::fromString($commandArguments['targetSucceedingSiblingNodeAggregateIdentifier'])
            : null;
        $targetNodeName = isset($commandArguments['targetNodeName'])
            ? NodeName::fromString($commandArguments['targetNodeName'])
            : null;

        $command = CopyNodesRecursively::createFromSubgraphAndStartNode(
            $subgraph,
            $currentNode,
            $targetDimensionSpacePoint,
            $initiatingUserIdentifier,
            NodeAggregateIdentifier::fromString($commandArguments['targetParentNodeAggregateIdentifier']),
            $targetSucceedingSiblingNodeAggregateIdentifier,
            $targetNodeName
        );
        $command = $command->withNodeAggregateIdentifierMapping(NodeAggregateIdentifierMapping::fromArray($commandArguments['nodeAggregateIdentifierMapping']));

        $this->lastCommandOrEventResult = $this->getContentRepository()->handle($command);
    }
}
