<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus\CreateAnnotationAction;
use MagoAssistant\Hypernode\Test\Unit\Fakes\FakeApiClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CreateAnnotationActionTest extends TestCase
{
    #[Test]
    public function itRefusesAnUnnamedAnnotationBeforeConfirmation(): void
    {
        $action = new CreateAnnotationAction(new FakeApiClient());

        self::assertSame(['error' => 'An annotation needs a name.'], $action->findRefusal(['name' => '  ']));
        self::assertNull($action->findRefusal(['name' => 'Deploy 1.2.3']));
    }

    #[Test]
    public function itCreatesTheAnnotationAtTheCurrentTimeWithTheNote(): void
    {
        $client = new FakeApiClient();

        $result = (new CreateAnnotationAction($client))->execute(
            ['name' => 'Cache flushed', 'metrics' => ['cpu', ''], 'note' => 'after price import'],
            1
        );

        self::assertTrue($result['created']);
        self::assertSame('Cache flushed', $client->createdAnnotations[0]['name']);
        self::assertSame(['cpu'], $client->createdAnnotations[0]['metrics']);
        self::assertSame(['source' => 'mago', 'note' => 'after price import'], $client->createdAnnotations[0]['metadata']);
        self::assertSame($client->createdAnnotations[0]['at'], $result['at']);
    }

    #[Test]
    public function itIsAWriteActionThatReportsApiFailures(): void
    {
        $action = new CreateAnnotationAction((new FakeApiClient())->failingWith('Hypernode API answered HTTP 500 for /v2/insights-annotation/create/'));

        self::assertFalse($action->isReadOnly());
        self::assertSame(
            'Hypernode API answered HTTP 500 for /v2/insights-annotation/create/',
            $action->execute(['name' => 'Deploy'], 1)['error']
        );
    }
}
