<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Projection;

use Neos\ContentRepository\Core\ContentRepository;

/**
 * Interface for a class that (asynchronously) triggers a catchup of affected projections after a
 * {@see ContentRepository::handle()} call.
 *
 * Usually, this (asynchronously) triggers {@see ProjectionInterface::catchUp()} via a subprocess or an event queue.
 *
 * @api
 */
interface ProjectionCatchUpTriggerInterface
{
    public function triggerCatchUp(Projections $projections): void;
}
