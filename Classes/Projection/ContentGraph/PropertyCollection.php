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

namespace Neos\ContentRepository\Core\Projection\ContentGraph;

use Neos\ContentRepository\Core\Feature\NodeModification\Dto\SerializedPropertyValues;
use Neos\ContentRepository\Core\Infrastructure\Property\PropertyConverter;

/**
 * The property collection implementation
 * @internal
 */
final class PropertyCollection implements PropertyCollectionInterface
{
    /**
     * Properties from Nodes
     */
    private SerializedPropertyValues $serializedPropertyValues;

    /**
     * @var ?array<string,mixed>
     */
    private ?array $deserializedPropertyValues = null;

    private PropertyConverter $propertyConverter;

    /**
     * @internal do not create from userspace
     */
    public function __construct(
        SerializedPropertyValues $serializedPropertyValues,
        PropertyConverter $propertyConverter
    ) {
        $this->serializedPropertyValues = $serializedPropertyValues;
        $this->propertyConverter = $propertyConverter;
    }

    public function offsetExists($offset): bool
    {
        return $this->serializedPropertyValues->propertyExists($offset);
    }

    public function offsetGet($offset): mixed
    {
        if (!$this->offsetExists($offset)) {
            return null;
        }
        if (!isset($this->deserializedPropertyValues[$offset])) {
            $serializedProperty = $this->serializedPropertyValues->getProperty($offset);
            if (!is_null($serializedProperty)) {
                $this->deserializedPropertyValues[$offset] = $this->propertyConverter->deserializePropertyValue(
                    $serializedProperty
                );
            }
        }

        return $this->deserializedPropertyValues[$offset];
    }

    public function offsetSet($offset, $value): never
    {
        throw new \RuntimeException("Do not use!");
    }

    public function offsetUnset($offset): never
    {
        throw new \RuntimeException("Do not use!");
    }

    /**
     * @return \Generator<string,mixed>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->serializedPropertyValues as $propertyName => $_) {
            yield $propertyName => $this->offsetGet($propertyName);
        }
    }

    public function serialized(): SerializedPropertyValues
    {
        return $this->serializedPropertyValues;
    }
}
