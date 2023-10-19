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

namespace Neos\ContentRepository\Core\Feature\NodeReferencing\Dto;

use JetBrains\PhpStorm\Internal\TentativeType;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateIds;

/**
 * Node references to write, supports arbitrary objects as reference property values.
 * Will be then converted to {@see SerializedNodeReferences} inside the events and persisted commands.
 *
 * We expect the value types to match the NodeType's property types (this is validated in the command handler).
 *
 * @implements \IteratorAggregate<int,NodeReferenceToWrite>
 * @api used as part of commands
 */
final class NodeReferencesToWrite implements \IteratorAggregate, \JsonSerializable
{
    /**
     * @var array<int,NodeReferenceToWrite>
     */
    public readonly array $references;

    private function __construct(NodeReferenceToWrite ...$references)
    {
        /** @var array<int,NodeReferenceToWrite> $references */
        $this->references = $references;
    }

    /**
     * @param array<int,NodeReferenceToWrite> $references
     */
    public static function fromReferences(array $references): self
    {
        return new self(...$references);
    }

    /**
     * @param array<int,array<string,mixed>> $values
     */
    public static function fromArray(array $values): self
    {
        return new self(...array_map(
            fn (array $serializedReference): NodeReferenceToWrite
                => NodeReferenceToWrite::fromArray($serializedReference),
            $values
        ));
    }

    public static function fromNodeAggregateIds(NodeAggregateIds $nodeAggregateIds): self
    {
        return new self(...array_map(
            fn (NodeAggregateId $nodeAggregateId): NodeReferenceToWrite
                => new NodeReferenceToWrite($nodeAggregateId, null),
            iterator_to_array($nodeAggregateIds)
        ));
    }

    /**
     * @throws \JsonException
     */
    public static function fromJsonString(string $jsonString): self
    {
        return self::fromArray(\json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return \ArrayIterator<int,NodeReferenceToWrite>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->references);
    }

    /**
     * @return array<int,NodeReferenceToWrite>
     */
    public function jsonSerialize(): array
    {
        return $this->references;
    }
}
