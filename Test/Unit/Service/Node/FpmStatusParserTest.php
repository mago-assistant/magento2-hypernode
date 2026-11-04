<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Service\Node;

use MagoAssistant\Hypernode\Service\Node\FpmStatusParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FpmStatusParserTest extends TestCase
{
    private const OUTPUT = <<<TXT
50570 IDLE   0.0s -  phpfpm    127.0.0.1       GET  magweb/status.php   (python-requests/2.28.1)
21143 IDLE   0.1s US 1.2.3.4  GET  magweb/status.php   (Hypernode heartbeat)
21144 RUNNING   4.2s NL 5.6.7.8  GET  /checkout/cart/?utm=x   (Mozilla/5.0 (X11; Linux x86_64))
21145 RUNNING  12.7s DE 9.9.9.9  POST /rest/V1/products   (GuzzleHttp/7)
TXT;

    #[Test]
    public function itCountsIdleAndActiveWorkers(): void
    {
        $result = (new FpmStatusParser())->parse(self::OUTPUT);

        self::assertSame(4, $result['total_workers']);
        self::assertSame(2, $result['idle_workers']);
        self::assertSame(2, $result['active_workers']);
        self::assertSame(50, $result['usage_percent']);
        self::assertSame(12.7, $result['longest_running_seconds']);
    }

    #[Test]
    public function itListsActiveRequestsLongestFirstWithoutIpsOrQueryStrings(): void
    {
        $requests = (new FpmStatusParser())->parse(self::OUTPUT)['active_requests'];

        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('/rest/V1/products', $requests[0]['path']);
        self::assertSame('GuzzleHttp', $requests[0]['user_agent']);
        self::assertSame('/checkout/cart/', $requests[1]['path']);
        self::assertSame(4.2, $requests[1]['seconds']);
        self::assertStringNotContainsString('5.6.7.8', json_encode($requests, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function itIgnoresLinesThatAreNotWorkers(): void
    {
        $result = (new FpmStatusParser())->parse("pool: www\n\n" . self::OUTPUT);

        self::assertSame(4, $result['total_workers']);
    }

    #[Test]
    public function itHandlesEmptyOutput(): void
    {
        $result = (new FpmStatusParser())->parse('');

        self::assertSame(0, $result['total_workers']);
        self::assertSame(0, $result['usage_percent']);
        self::assertSame([], $result['active_requests']);
    }
}
