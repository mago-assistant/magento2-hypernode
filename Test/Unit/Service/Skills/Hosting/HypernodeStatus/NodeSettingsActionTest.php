<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus\NodeSettingsAction;
use MagoAssistant\Hypernode\Test\Unit\Fakes\FakeApiClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NodeSettingsActionTest extends TestCase
{
    #[Test]
    public function itGroupsTheListedSettings(): void
    {
        $client = (new FakeApiClient())->withApp([
            'mailhog_enabled' => true,
            'varnish_enabled' => false,
            'opensearch_version' => '2.12',
            'alternative_php_versions' => ['8.3', '8.2'],
            'redis_persistent_instance' => null,
        ]);

        $result = (new NodeSettingsAction($client))->execute([], 1);

        self::assertTrue($result['mail']['mailhog_enabled']);
        self::assertFalse($result['cache']['varnish_enabled']);
        self::assertNull($result['cache']['redis_persistent_instance']);
        self::assertSame('2.12', $result['search']['opensearch_version']);
        self::assertSame('8.3, 8.2', $result['stack']['alternative_php_versions']);
        self::assertArrayNotHasKey('queue', $result);
    }

    #[Test]
    public function itNeverPassesSecretsOrContactDetails(): void
    {
        $client = (new FakeApiClient())->withApp([
            'mailhog_enabled' => false,
            'varnish_secret' => 'varnish-secret',
            'tideways_api_key' => 'tideways-key',
            'blackfire_server_token' => 'blackfire-token',
            'ip' => '203.0.113.7',
            'ssh_keys' => [['key' => 'ssh-ed25519 AAAA']],
            'owner' => ['email' => 'owner@example.com'],
        ]);

        $result = (new NodeSettingsAction($client))->execute([], 1);

        self::assertSame(['mail' => ['mailhog_enabled' => false]], $result);
    }

    #[Test]
    public function itReportsAnUnreachableApi(): void
    {
        $result = (new NodeSettingsAction((new FakeApiClient())->failingWith('API down')))->execute([], 1);

        self::assertSame(['error' => 'API down'], $result);
    }
}
