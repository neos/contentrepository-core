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

namespace Neos\ContentRepository\Core\Tests\Behavior\Features\Helper;

use Neos\Cache\CacheAwareInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamIdentifier;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIdentifier;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;

/**
 * The node discriminator value object
 *
 * Represents the identity of a specific node in the content graph and is thus composed of
 * * the content stream the node exists in
 * * the node's aggregate's external identifier
 * * the dimension space point the node originates in within its aggregate
 */
final class NodeDiscriminator implements CacheAwareInterface, \JsonSerializable
{
    private ContentStreamIdentifier $contentStreamIdentifier;

    private NodeAggregateIdentifier $nodeAggregateIdentifier;

    private OriginDimensionSpacePoint $originDimensionSpacePoint;

    private function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        OriginDimensionSpacePoint $originDimensionSpacePoint
    ) {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->originDimensionSpacePoint = $originDimensionSpacePoint;
    }

    public static function fromShorthand(string $shorthand): self
    {
        list($contentStreamIdentifier, $nodeAggregateIdentifier, $originDimensionSpacePoint) = explode(';', $shorthand);

        return new self(
            ContentStreamIdentifier::fromString($contentStreamIdentifier),
            NodeAggregateIdentifier::fromString($nodeAggregateIdentifier),
            OriginDimensionSpacePoint::fromJsonString($originDimensionSpacePoint)
        );
    }

    public static function fromNode(Node $node): self
    {
        return new NodeDiscriminator(
            $node->subgraphIdentity->contentStreamIdentifier,
            $node->nodeAggregateIdentifier,
            $node->originDimensionSpacePoint
        );
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

    public function getCacheEntryIdentifier(): string
    {
        return sha1(json_encode($this));
    }

    public function equals(NodeDiscriminator $other): bool
    {
        return $this->contentStreamIdentifier->equals($other->getContentStreamIdentifier())
            && $this->getNodeAggregateIdentifier()->equals($other->getNodeAggregateIdentifier())
            && $this->getOriginDimensionSpacePoint()->equals($other->getOriginDimensionSpacePoint());
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'nodeAggregateIdentifier' => $this->nodeAggregateIdentifier,
            'originDimensionSpacePoint' => $this->originDimensionSpacePoint
        ];
    }

    public function __toString(): string
    {
        return $this->getCacheEntryIdentifier();
    }
}
