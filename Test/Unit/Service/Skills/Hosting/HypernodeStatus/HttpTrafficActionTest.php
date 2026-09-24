<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Hypernode\Service\Node\AccessLogReader;
use MagoAssistant\Hypernode\Service\Config;
use MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus\HttpTrafficAction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HttpTrafficActionTest extends TestCase
{
    #[Test]
    public function itDoesNotReadTheLogOffTheNode(): void
    {
        $reader = $this->createMock(AccessLogReader::class);
        $reader->expects(self::never())->method('summarize');

        $result = (new HttpTrafficAction($reader, $this->config(false)))->execute(['minutes' => 30], 1);

        self::assertStringContainsString('Insights', $result['error']);
    }

    #[Test]
    public function itClampsTheWindowAndPassesItToTheReader(): void
    {
        $reader = $this->createMock(AccessLogReader::class);
        $reader->expects(self::once())->method('summarize')->with(1440)->willReturn(['requests' => 3]);

        $result = (new HttpTrafficAction($reader, $this->config(true)))->execute(['minutes' => 99999], 1);

        self::assertSame(3, $result['requests']);
    }

    #[Test]
    public function itAnswersWithTheReaderErrorInsteadOfThrowing(): void
    {
        $reader = $this->createStub(AccessLogReader::class);
        $reader->method('summarize')->willThrowException(new \RuntimeException('The access log is not readable from PHP.'));

        $result = (new HttpTrafficAction($reader, $this->config(true)))->execute([], 1);

        self::assertSame('The access log is not readable from PHP.', $result['error']);
    }

    private function config(bool $onNode): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isOnHypernode')->willReturn($onNode);
        $config->method('getNotOnNodeMessage')->willReturn('Magento does not run on the Hypernode itself; see Hypernode Insights.');

        return $config;
    }
}
