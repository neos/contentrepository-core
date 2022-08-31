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

namespace Neos\ContentRepository\Core\Feature\NodeCreation\Event;

use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamIdentifier;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Feature\Common\EmbedsContentStreamAndNodeAggregateIdentifier;
use Neos\ContentRepository\Core\Feature\Common\PublishableToOtherContentStreamsInterface;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateClassification;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\SerializedPropertyValues;
use Neos\ContentRepository\Core\SharedModel\User\UserIdentifier;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIdentifier;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\NodeType\NodeTypeName;

/**
 * A node aggregate with its initial node was created
 *
 * @api events are the persistence-API of the content repository
 */
final class NodeAggregateWithNodeWasCreated implements
    EventInterface,
    PublishableToOtherContentStreamsInterface,
    EmbedsContentStreamAndNodeAggregateIdentifier
{
    public function __construct(
        public readonly ContentStreamIdentifier $contentStreamIdentifier,
        public readonly NodeAggregateIdentifier $nodeAggregateIdentifier,
        public readonly NodeTypeName $nodeTypeName,
        public readonly OriginDimensionSpacePoint $originDimensionSpacePoint,
        public readonly DimensionSpacePointSet $coveredDimensionSpacePoints,
        public readonly NodeAggregateIdentifier $parentNodeAggregateIdentifier,
        public readonly ?NodeName $nodeName,
        public readonly SerializedPropertyValues $initialPropertyValues,
        public readonly NodeAggregateClassification $nodeAggregateClassification,
        public readonly UserIdentifier $initiatingUserIdentifier,
        public readonly ?NodeAggregateIdentifier $succeedingNodeAggregateIdentifier = null
    ) {
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    public function getOriginDimensionSpacePoint(): OriginDimensionSpacePoint
    {
        return $this->originDimensionSpacePoint;
    }

    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStreamIdentifier): self
    {
        return new self(
            $targetContentStreamIdentifier,
            $this->nodeAggregateIdentifier,
            $this->nodeTypeName,
            $this->originDimensionSpacePoint,
            $this->coveredDimensionSpacePoints,
            $this->parentNodeAggregateIdentifier,
            $this->nodeName,
            $this->initialPropertyValues,
            $this->nodeAggregateClassification,
            $this->initiatingUserIdentifier,
            $this->succeedingNodeAggregateIdentifier
        );
    }

    public static function fromArray(array $values): self
    {
        return new self(
            ContentStreamIdentifier::fromString($values['contentStreamIdentifier']),
            NodeAggregateIdentifier::fromString($values['nodeAggregateIdentifier']),
            NodeTypeName::fromString($values['nodeTypeName']),
            OriginDimensionSpacePoint::fromArray($values['originDimensionSpacePoint']),
            DimensionSpacePointSet::fromArray($values['coveredDimensionSpacePoints']),
            NodeAggregateIdentifier::fromString($values['parentNodeAggregateIdentifier']),
            isset($values['nodeName']) ? NodeName::fromString($values['nodeName']) : null,
            SerializedPropertyValues::fromArray($values['initialPropertyValues']),
            NodeAggregateClassification::from($values['nodeAggregateClassification']),
            UserIdentifier::fromString($values['initiatingUserIdentifier']),
            isset($values['succeedingNodeAggregateIdentifier'])
                ? NodeAggregateIdentifier::fromString($values['succeedingNodeAggregateIdentifier'])
                : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'nodeAggregateIdentifier' => $this->nodeAggregateIdentifier,
            'nodeTypeName' => $this->nodeTypeName,
            'originDimensionSpacePoint' => $this->originDimensionSpacePoint,
            'coveredDimensionSpacePoints' => $this->coveredDimensionSpacePoints,
            'parentNodeAggregateIdentifier' => $this->parentNodeAggregateIdentifier,
            'nodeName' => $this->nodeName,
            'initialPropertyValues' => $this->initialPropertyValues,
            'nodeAggregateClassification' => $this->nodeAggregateClassification,
            'initiatingUserIdentifier' => $this->initiatingUserIdentifier,
            'succeedingNodeAggregateIdentifier' => $this->succeedingNodeAggregateIdentifier
        ];
    }
}
