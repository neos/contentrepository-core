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

namespace Neos\ContentRepository\Core\Feature\NodeMove\Event;

/**
 * @implements \IteratorAggregate<int,NodeMoveMapping>
 * @api DTO of {@see NodeAggregateWasMoved} event
 */
final class NodeMoveMappings implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @var array<int,NodeMoveMapping>
     */
    private array $mappings;

    /**
     * @var \ArrayIterator<int,NodeMoveMapping>
     */
    private \ArrayIterator $iterator;

    /**
     * @param array<int,NodeMoveMapping> $values
     */
    private function __construct(array $values)
    {
        $this->mappings = $values;
        $this->iterator = new \ArrayIterator($values);
    }

    /**
     * @param array<int|string,array<string,mixed>|NodeMoveMapping> $mappings
     */
    public static function fromArray(array $mappings): self
    {
        $processedMappings = [];
        foreach ($mappings as $mapping) {
            if (is_array($mapping)) {
                $processedMappings[] = NodeMoveMapping::fromArray($mapping);
            } elseif ($mapping instanceof NodeMoveMapping) {
                $processedMappings[] = $mapping;
            } else {
                /** @var mixed $mapping */
                throw new \InvalidArgumentException(sprintf(
                    'Invalid NodeMoveMapping. Expected instance of %s, got: %s',
                    NodeMoveMapping::class,
                    is_object($mapping) ? get_class($mapping) : gettype($mapping)
                ), 1547811318);
            }
        }
        return new self($processedMappings);
    }

    /**
     * @return \ArrayIterator<int,NodeMoveMapping>
     */
    public function getIterator(): \ArrayIterator
    {
        return $this->iterator;
    }

    public function count(): int
    {
        return count($this->mappings);
    }

    /**
     * @return array<int,NodeMoveMapping>
     */
    public function jsonSerialize(): array
    {
        return $this->mappings;
    }
}
