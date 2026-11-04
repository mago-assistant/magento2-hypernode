<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Service;

use MagoAssistant\Hypernode\Service\Config;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    #[Test]
    public function itReadsAppNameAndTokenFromAppJson(): void
    {
        $config = $this->config('{"hn_fqdn": "commerce.hypernode.io", "hn_domain": "hypernode.io", '
            . '"hypernode_api_token": " abc123\n"}');

        self::assertSame('commerce', $config->getAppName());
        self::assertSame('abc123', $config->getApiToken());
        self::assertTrue($config->isOnHypernode());
        self::assertTrue($config->isApiConfigured());
    }

    #[Test]
    public function itUsesTheBrancherNameOnABrancherNode(): void
    {
        $config = $this->config('{"hn_fqdn": "commerce-eph123456.hypernode.io", "hn_domain": "hypernode.io"}');

        self::assertSame('commerce-eph123456', $config->getAppName());
        self::assertFalse($config->isApiConfigured());
    }

    #[Test]
    public function itIsNotOnTheNodeWithoutAppJson(): void
    {
        $config = $this->config(null);

        self::assertSame('', $config->getAppName());
        self::assertSame('', $config->getApiToken());
        self::assertFalse($config->isOnHypernode());
        self::assertFalse($config->isApiConfigured());
    }

    #[Test]
    public function itTreatsBrokenJsonAsMissing(): void
    {
        self::assertFalse($this->config('{not json')->isOnHypernode());
    }

    private function config(?string $contents): Config
    {
        $driver = $this->createStub(FileDriver::class);
        $driver->method('isReadable')->willReturn($contents !== null);
        $driver->method('fileGetContents')->willReturn((string)$contents);

        return new Config($driver, new Json());
    }
}
