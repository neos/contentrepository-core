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
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamIdentifier;
use Neos\ContentRepository\Core\Feature\ContentStreamForking\Command\ForkContentStream;
use Neos\ContentRepository\Core\Feature\ContentStreamCommandHandler;
use Neos\ContentRepository\Core\SharedModel\User\UserIdentifier;

/**
 * The content stream forking feature trait for behavioral tests
 */
trait ContentStreamForking
{
    abstract protected function getContentRepository(): ContentRepository;

    abstract protected function getCurrentContentStreamIdentifier(): ?ContentStreamIdentifier;

    abstract protected function getCurrentUserIdentifier(): ?UserIdentifier;

    abstract protected function readPayloadTable(TableNode $payloadTable): array;

    /**
     * @Given /^the command ForkContentStream is executed with payload:$/
     * @param TableNode $payloadTable
     * @throws \Exception
     */
    public function theCommandForkContentStreamIsExecutedWithPayload(TableNode $payloadTable): void
    {
        $commandArguments = $this->readPayloadTable($payloadTable);
        $sourceContentStreamIdentifier = isset($commandArguments['sourceContentStreamIdentifier'])
            ? ContentStreamIdentifier::fromString($commandArguments['sourceContentStreamIdentifier'])
            : $this->getCurrentContentStreamIdentifier();
        $initiatingUserIdentifier = isset($commandArguments['initiatingUserIdentifier'])
            ? UserIdentifier::fromString($commandArguments['initiatingUserIdentifier'])
            : $this->getCurrentUserIdentifier();

        $command = new ForkContentStream(
            ContentStreamIdentifier::fromString($commandArguments['contentStreamIdentifier']),
            $sourceContentStreamIdentifier,
            $initiatingUserIdentifier
        );

        $this->lastCommandOrEventResult = $this->getContentRepository()->handle($command);
    }
}
