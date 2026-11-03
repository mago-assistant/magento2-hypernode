<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Service\Api;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\Serialize\Serializer\Json;
use MagoAssistant\Hypernode\Service\Api\ApiClient;
use MagoAssistant\Hypernode\Service\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ApiClientTest extends TestCase
{
    #[Test]
    public function itFetchesTheSettingDescriptionsOnceAndThenReadsTheCache(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->expects(self::once())->method('get')->with(Config::API_URL . '/v2/app/eav_descriptions/');
        $curl->method('getStatus')->willReturn(200);
        $curl->method('getBody')->willReturn('{"php_version":["8.3","8.4"]}');
        $curlFactory = $this->createStub(CurlFactory::class);
        $curlFactory->method('create')->willReturn($curl);

        $config = $this->createStub(Config::class);
        $config->method('getApiToken')->willReturn('token');

        $client = new ApiClient($curlFactory, new Json(), $config, $this->createStub(PackageInfo::class), $this->memoryCache());

        self::assertSame(['php_version' => ['8.3', '8.4']], $client->getSettingDescriptions());
        self::assertSame(['php_version' => ['8.3', '8.4']], $client->getSettingDescriptions());
    }

    private function memoryCache(): CacheInterface
    {
        $store = [];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(static function (string $key) use (&$store) {
            return $store[$key] ?? false;
        });
        $cache->method('save')->willReturnCallback(static function (string $data, string $key) use (&$store): bool {
            $store[$key] = $data;

            return true;
        });

        return $cache;
    }
}
