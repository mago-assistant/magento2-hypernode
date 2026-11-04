<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Hypernode\Service\Config;
use MagoAssistant\Hypernode\Service\Node\SystemMetrics;
use MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus\ServerOverviewAction;
use MagoAssistant\Hypernode\Test\Unit\Fakes\FakeApiClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ServerOverviewActionTest extends TestCase
{
    #[Test]
    public function itReportsNothingButTheConfigurationHintWhenTheApiIsNotConfigured(): void
    {
        $metrics = $this->createMock(SystemMetrics::class);
        $metrics->expects(self::never())->method('collect');

        $result = (new ServerOverviewAction($metrics, new FakeApiClient(), $this->config(false)))->execute([], 1);

        self::assertSame(['error'], array_keys($result));
        self::assertStringContainsString('not configured', $result['error']);
    }

    #[Test]
    public function itCombinesLocalMetricsWithThePlanFromTheApi(): void
    {
        $metrics = $this->createStub(SystemMetrics::class);
        $metrics->method('collect')->willReturn(['load_1m' => 0.5, 'cpu_count' => 2]);
        $client = (new FakeApiClient())->withApp([
            'name' => 'yourshop',
            'flavor' => ['name' => 'Falcon S', 'redis_size' => '1024'],
            'php_version' => '8.4',
            'account_user' => ['email' => 'owner@example.com'],
        ]);

        $result = (new ServerOverviewAction($metrics, $client, $this->config(true)))->execute([], 1);

        self::assertSame(0.5, $result['server']['load_1m']);
        self::assertSame('Falcon S', $result['hypernode']['plan']);
        self::assertSame(1024, $result['hypernode']['redis_size_mb']);
        self::assertSame('ok', $result['hypernode_api']);
        self::assertStringNotContainsString('owner@example.com', json_encode($result, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function itKeepsLocalMetricsWhenTheConfiguredApiFails(): void
    {
        $metrics = $this->createStub(SystemMetrics::class);
        $metrics->method('collect')->willReturn(['load_1m' => 0.5]);
        $client = (new FakeApiClient())->failingWith('The Hypernode API rejected the token (HTTP 401).');

        $result = (new ServerOverviewAction($metrics, $client, $this->config(true)))->execute([], 1);

        self::assertSame(0.5, $result['server']['load_1m']);
        self::assertSame('unavailable: The Hypernode API rejected the token (HTTP 401).', $result['hypernode_api']);
        self::assertArrayNotHasKey('hypernode', $result);
    }

    #[Test]
    public function itLeavesOutTheLocalMetricsWhenMagentoDoesNotRunOnTheNode(): void
    {
        $metrics = $this->createMock(SystemMetrics::class);
        $metrics->expects(self::never())->method('collect');
        $client = (new FakeApiClient())->withApp(['name' => 'yourshop', 'flavor' => ['name' => 'Falcon S']]);

        $result = (new ServerOverviewAction($metrics, $client, $this->config(true, false)))->execute([], 1);

        self::assertArrayNotHasKey('server', $result);
        self::assertStringContainsString('Insights', $result['server_note']);
        self::assertSame('Falcon S', $result['hypernode']['plan']);
    }

    private function config(bool $configured, bool $onNode = true): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isApiConfigured')->willReturn($configured);
        $config->method('isOnHypernode')->willReturn($onNode);
        $config->method('getAppName')->willReturn($configured ? 'yourshop' : '');
        $config->method('getNotConfiguredMessage')->willReturn('The Hypernode API is not configured: ...');
        $config->method('getNotOnNodeMessage')->willReturn('Magento does not run on the Hypernode itself; see Hypernode Insights.');

        return $config;
    }
}
