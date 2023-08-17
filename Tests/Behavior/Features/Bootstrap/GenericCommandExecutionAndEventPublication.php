<?php
declare(strict_types=1);
namespace Neos\ContentRepository\Core\Tests\Behavior\Features\Bootstrap;

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
use Neos\ContentRepository\Core\CommandHandler\CommandResult;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\EventStore\Events;
use Neos\ContentRepository\Core\EventStore\EventsToPublish;
use Neos\ContentRepository\Core\Feature\NodeMove\Command\MoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\ContentStreamForking\Command\ForkContentStream;
use Neos\ContentRepository\Core\Feature\NodeRenaming\Command\ChangeNodeAggregateName;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNodeAndSerializedProperties;
use Neos\ContentRepository\Core\Feature\NodeDisabling\Command\DisableNodeAggregate;
use Neos\ContentRepository\Core\Feature\NodeDisabling\Command\EnableNodeAggregate;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Command\SetNodeReferences;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetSerializedNodeProperties;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateRootWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Command\PublishIndividualNodesFromWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Command\PublishWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspaceRebase\Command\RebaseWorkspace;
use Neos\ContentRepository\Core\Tests\Behavior\Features\Bootstrap\Helpers\ContentRepositoryInternals;
use Neos\ContentRepository\TestSuite\Behavior\Features\Bootstrap\CRTestSuiteRuntimeVariables;
use Neos\EventStore\Model\Event;
use Neos\EventStore\Model\Event\StreamName;
use Neos\EventStore\Model\EventEnvelope;
use Neos\EventStore\Model\EventStream\ExpectedVersion;
use Neos\EventStore\Model\EventStream\VirtualStreamName;
use Neos\Utility\Arrays;
use PHPUnit\Framework\Assert;

/**
 * The content stream forking feature trait for behavioral tests
 */
trait GenericCommandExecutionAndEventPublication
{
    use CRTestSuiteRuntimeVariables;

    private ?array $currentEventStreamAsArray = null;

    protected CommandResult|null $lastCommandOrEventResult = null;

    protected ?\Exception $lastCommandException = null;

    abstract protected function readPayloadTable(TableNode $payloadTable): array;

    abstract protected function getContentRepositoryInternals(): ContentRepositoryInternals;

    /**
     * @When /^the command "([^"]*)" is executed with payload:$/
     * @Given /^the command "([^"]*)" was executed with payload:$/
     * @param string $shortCommandName
     * @param TableNode|null $payloadTable
     * @param null $commandArguments
     * @throws \Exception
     */
    public function theCommandIsExecutedWithPayload(string $shortCommandName, TableNode $payloadTable = null, $commandArguments = null)
    {
        $commandClassName = self::resolveShortCommandName($shortCommandName);
        if ($commandArguments === null && $payloadTable !== null) {
            $commandArguments = $this->readPayloadTable($payloadTable);
        }

        if (isset($commandArguments['propertyValues.dateProperty'])) {
            // special case to test Date type conversion
            $commandArguments['propertyValues']['dateProperty'] = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $commandArguments['propertyValues.dateProperty']);
        }

        if (!method_exists($commandClassName, 'fromArray')) {
            throw new \InvalidArgumentException(sprintf('Command "%s" does not implement a static "fromArray" constructor', $commandClassName), 1545564621);
        }

        $command = $commandClassName::fromArray($commandArguments);

        $this->lastCommandOrEventResult = $this->currentContentRepository->handle($command);
    }

    /**
     * @When /^the command "([^"]*)" is executed with payload and exceptions are caught:$/
     */
    public function theCommandIsExecutedWithPayloadAndExceptionsAreCaught($shortCommandName, TableNode $payloadTable)
    {
        try {
            $this->theCommandIsExecutedWithPayload($shortCommandName, $payloadTable);
        } catch (\Exception $exception) {
            $this->lastCommandException = $exception;
        }
    }

    /**
     * @param $shortCommandName
     * @return array
     * @throws \Exception
     */
    protected static function resolveShortCommandName($shortCommandName): string
    {
        switch ($shortCommandName) {
            case 'CreateRootWorkspace':
                return CreateRootWorkspace::class;
            case 'CreateWorkspace':
                return CreateWorkspace::class;
            case 'PublishWorkspace':
                return PublishWorkspace::class;
            case 'PublishIndividualNodesFromWorkspace':
                return PublishIndividualNodesFromWorkspace::class;
            case 'RebaseWorkspace':
                return RebaseWorkspace::class;
            case 'CreateNodeAggregateWithNodeAndSerializedProperties':
                return CreateNodeAggregateWithNodeAndSerializedProperties::class;
            case 'ForkContentStream':
                return ForkContentStream::class;
            case 'ChangeNodeAggregateName':
                return ChangeNodeAggregateName::class;
            case 'SetSerializedNodeProperties':
                return SetSerializedNodeProperties::class;
            case 'DisableNodeAggregate':
                return DisableNodeAggregate::class;
            case 'EnableNodeAggregate':
                return EnableNodeAggregate::class;
            case 'MoveNodeAggregate':
                return MoveNodeAggregate::class;
            case 'SetNodeReferences':
                return SetNodeReferences::class;

            default:
                throw new \Exception('The short command name "' . $shortCommandName . '" is currently not supported by the tests.');
        }
    }

    /**
     * @Given /^the Event "([^"]*)" was published to stream "([^"]*)" with payload:$/
     * @param $eventType
     * @param $streamName
     * @param TableNode $payloadTable
     * @throws \Exception
     */
    public function theEventWasPublishedToStreamWithPayload(string $eventType, string $streamName, TableNode $payloadTable)
    {
        $eventPayload = $this->readPayloadTable($payloadTable);
        $this->publishEvent($eventType, StreamName::fromString($streamName), $eventPayload);
    }

    /**
     * @param $eventType
     * @param StreamName $streamName
     * @param $eventPayload
     * @throws \Exception
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    protected function publishEvent(string $eventType, StreamName $streamName, array $eventPayload): void
    {
        $artificiallyConstructedEvent = new Event(
            Event\EventId::create(),
            Event\EventType::fromString($eventType),
            Event\EventData::fromString(json_encode($eventPayload)),
            Event\EventMetadata::fromArray([])
        );
        $eventPersister = (new \ReflectionClass($this->currentContentRepository))->getProperty('eventPersister')
            ->getValue($this->currentContentRepository);
        $eventNormalizer = (new \ReflectionClass($eventPersister))->getProperty('eventNormalizer')
            ->getValue($eventPersister);
        $event = $eventNormalizer->denormalize($artificiallyConstructedEvent);

        $this->lastCommandOrEventResult = $eventPersister->publishEvents(new EventsToPublish(
            $streamName,
            Events::with($event),
            ExpectedVersion::ANY()
        ));
    }

    /**
     * @Then /^the last command should have thrown an exception of type "([^"]*)"(?: with code (\d*))?$/
     * @param string $shortExceptionName
     * @param int|null $expectedCode
     * @throws \ReflectionException
     */
    public function theLastCommandShouldHaveThrown(string $shortExceptionName, ?int $expectedCode = null)
    {
        Assert::assertNotNull($this->lastCommandException, 'Command did not throw exception');
        $lastCommandExceptionShortName = (new \ReflectionClass($this->lastCommandException))->getShortName();
        Assert::assertSame($shortExceptionName, $lastCommandExceptionShortName, sprintf('Actual exception: %s (%s): %s', get_class($this->lastCommandException), $this->lastCommandException->getCode(), $this->lastCommandException->getMessage()));
        if (!is_null($expectedCode)) {
            Assert::assertSame($expectedCode, $this->lastCommandException->getCode(), sprintf(
                'Expected exception code %s, got exception code %s instead',
                $expectedCode,
                $this->lastCommandException->getCode()
            ));
        }
    }

    /**
     * @Then /^I expect exactly (\d+) events? to be published on stream "([^"]*)"$/
     * @param int $numberOfEvents
     * @param string $streamName
     */
    public function iExpectExactlyEventToBePublishedOnStream(int $numberOfEvents, string $streamName)
    {
        $streamName = StreamName::fromString($streamName);
        $stream = $this->getEventStore()->load($streamName);
        $this->currentEventStreamAsArray = iterator_to_array($stream, false);
        Assert::assertEquals($numberOfEvents, count($this->currentEventStreamAsArray), 'Number of events did not match');
    }

    /**
     * @Then /^I expect exactly (\d+) events? to be published on stream with prefix "([^"]*)"$/
     * @param int $numberOfEvents
     * @param string $streamName
     */
    public function iExpectExactlyEventToBePublishedOnStreamWithPrefix(int $numberOfEvents, string $streamName)
    {
        $streamName = VirtualStreamName::forCategory($streamName);

        $stream = $this->getEventStore()->load($streamName);
        $this->currentEventStreamAsArray = iterator_to_array($stream, false);
        Assert::assertEquals($numberOfEvents, count($this->currentEventStreamAsArray), 'Number of events did not match');
    }

    /**
     * @Then /^event at index (\d+) is of type "([^"]*)" with payload:/
     * @param int $eventNumber
     * @param string $eventType
     * @param TableNode $payloadTable
     */
    public function eventNumberIs(int $eventNumber, string $eventType, TableNode $payloadTable)
    {
        if ($this->currentEventStreamAsArray === null) {
            Assert::fail('Step \'I expect exactly ? events to be published on stream "?"\' was not executed');
        }

        Assert::assertArrayHasKey($eventNumber, $this->currentEventStreamAsArray, 'Event at index does not exist');

        $actualEvent = $this->currentEventStreamAsArray[$eventNumber];
        assert($actualEvent instanceof EventEnvelope);

        Assert::assertNotNull($actualEvent, sprintf('Event with number %d not found', $eventNumber));
        Assert::assertEquals($eventType, $actualEvent->event->type->value, 'Event Type does not match: "' . $actualEvent->event->type->value . '" !== "' . $eventType . '"');

        $actualEventPayload = json_decode($actualEvent->event->data->value, true);

        foreach ($payloadTable->getHash() as $assertionTableRow) {
            $key = $assertionTableRow['Key'];
            $actualValue = Arrays::getValueByPath($actualEventPayload, $key);

            if ($key === 'affectedDimensionSpacePoints') {
                $expected = DimensionSpacePointSet::fromJsonString($assertionTableRow['Expected']);
                $actual = DimensionSpacePointSet::fromArray($actualValue);
                Assert::assertTrue($expected->equals($actual), 'Actual Dimension Space Point set "' . json_encode($actualValue) . '" does not match expected Dimension Space Point set "' . $assertionTableRow['Expected'] . '"');
            } else {
                Assert::assertJsonStringEqualsJsonString($assertionTableRow['Expected'], json_encode($actualValue));
            }
        }
    }

    /**
     * @Then /^event metadata at index (\d+) is:/
     * @param int $eventNumber
     * @param TableNode $metadataTable
     */
    public function eventMetadataAtNumberIs(int $eventNumber, TableNode $metadataTable)
    {
        if ($this->currentEventStreamAsArray === null) {
            Assert::fail('Step \'I expect exactly ? events to be published on stream "?"\' was not executed');
        }

        Assert::assertArrayHasKey($eventNumber, $this->currentEventStreamAsArray, 'Event at index does not exist');

        $actualEvent = $this->currentEventStreamAsArray[$eventNumber];
        assert($actualEvent instanceof EventEnvelope);

        Assert::assertNotNull($actualEvent, sprintf('Event with number %d not found', $eventNumber));

        $actualEventMetadata = $actualEvent->event->metadata->value;
        foreach ($metadataTable->getHash() as $assertionTableRow) {
            $key = $assertionTableRow['Key'];
            $actualValue = Arrays::getValueByPath($actualEventMetadata, $key);
            Assert::assertJsonStringEqualsJsonString($assertionTableRow['Expected'], json_encode($actualValue));
        }
    }
}
