<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Projection\ContentGraph\Filter;

use Neos\ContentRepository\Core\Projection\ContentGraph\NodeTypeConstraints;
use Neos\ContentRepository\Core\SharedModel\Node\ReferenceName;

/**
 * Immutable filter DTO for {@see ContentSubgraphInterface::findReferences()}
 *
 * Example:
 *
 * FindReferencesFilter::create()->with(referenceName: 'someName');
 *
 * @api for the factory methods; NOT for the inner state.
 */
final class FindReferencesFilter
{
    /**
     * @internal (the properties themselves are readonly; only the write-methods are API.
     */
    private function __construct(
        public readonly ?ReferenceName $referenceName,
    ) {
    }

    public static function create(): self
    {
        return new self(null);
    }

    public static function referenceName(ReferenceName|string $referenceName): self
    {
        return self::create()->with(referenceName: $referenceName);
    }

    /**
     * Returns a new instance with the specified additional filter options
     *
     * Note: The signature of this method might be extended in the future, so it should always be used with named arguments
     * @see https://www.php.net/manual/en/functions.arguments.php#functions.named-arguments
     */
    public function with(
        ReferenceName|string $referenceName = null,
    ): self {
        if (is_string($referenceName)) {
            $referenceName = ReferenceName::fromString($referenceName);
        }
        return new self(
            $referenceName ?? $this->referenceName,
        );
    }
}
