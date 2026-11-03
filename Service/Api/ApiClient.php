<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Api;

use MagoAssistant\Hypernode\Service\Config;
use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Module\PackageInfo;
use Magento\Framework\Serialize\Serializer\Json;

class ApiClient implements ApiClientInterface
{
    private const USER_AGENT = 'MagoAssistant-Mago';
    private const MODULE = 'MagoAssistant_Hypernode';
    private const SETTINGS_CACHE_KEY = 'mago_hypernode_setting_descriptions';
    private const SETTINGS_CACHE_LIFETIME = 86400;

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly Config $config,
        private readonly PackageInfo $packageInfo,
        private readonly CacheInterface $cache
    ) {
    }

    public function getApp(): array
    {
        return $this->getJson('/v2/app/' . $this->appName() . '/');
    }

    public function getSettingDescriptions(): array
    {
        // The same for every app and rarely changed, so one call a day is enough.
        $cached = $this->cache->load(self::SETTINGS_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            $decoded = $this->json->unserialize($cached);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $descriptions = $this->getJson('/v2/app/eav_descriptions/');
        if ($descriptions !== []) {
            $this->cache->save(
                (string)$this->json->serialize($descriptions),
                self::SETTINGS_CACHE_KEY,
                [ConfigCache::CACHE_TAG],
                self::SETTINGS_CACHE_LIFETIME
            );
        }

        return $descriptions;
    }

    public function getFpmStatus(): string
    {
        // A NATS command: the API only accepts POST here (GET answers 405), with an empty body.
        $data = $this->request('POST', '/v2/nats/' . $this->appName() . '/hypernode.show-fpm-status');
        if (isset($data['status']) && (int)$data['status'] !== 200) {
            throw new ApiException('The node did not answer the FPM status request: ' . ($data['message'] ?? 'unknown'));
        }

        return (string)($data['data'] ?? '');
    }

    public function getFlows(): array
    {
        // Trailing slash matters: without it the API answers 301, which this client does not follow.
        return $this->getJson('/logbook/v1/logbooks/' . $this->appName() . '/flows/');
    }

    public function listAnnotations(): array
    {
        return $this->getJson('/v2/insights-annotation/?app=' . $this->appName());
    }

    private function appName(): string
    {
        $appName = $this->config->getAppName();
        if ($appName === '' || !preg_match('/^[a-z0-9-]+$/i', $appName)) {
            throw new ApiException($this->config->getNotConfiguredMessage());
        }

        return $appName;
    }

    private function getJson(string $path): array
    {
        return $this->request('GET', $path);
    }

    private function request(string $method, string $path): array
    {
        $token = $this->config->getApiToken();
        if ($token === '') {
            throw new ApiException($this->config->getNotConfiguredMessage());
        }

        $url = Config::API_URL . $path;
        $curl = $this->newCurl($token);

        try {
            if ($method === 'POST') {
                $curl->post($url, '');
            } else {
                $curl->get($url);
            }
        } catch (\Throwable $e) {
            throw new ApiException('Could not reach the Hypernode API: ' . $e->getMessage(), 0, $e);
        }

        $status = $curl->getStatus();
        $responseBody = $curl->getBody();
        if ($status === 401 || $status === 403) {
            throw new ApiException('The Hypernode API rejected the token (HTTP ' . $status . ').');
        }
        if ($status === 404) {
            throw new ApiException('The Hypernode API does not know app "' . $this->config->getAppName() . '".');
        }
        if ($status < 200 || $status >= 300) {
            throw new ApiException('Hypernode API answered HTTP ' . $status . ' for ' . $path);
        }

        if (trim($responseBody) === '') {
            return [];
        }

        try {
            $decoded = $this->json->unserialize($responseBody);
        } catch (\Throwable $e) {
            throw new ApiException('The Hypernode API returned invalid JSON for ' . $path, 0, $e);
        }

        return is_array($decoded) ? $decoded : ['value' => $decoded];
    }

    private function newCurl(string $token): Curl
    {
        $curl = $this->curlFactory->create();
        $curl->setOptions([
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('Accept-Language', 'en-US');
        $curl->addHeader('Authorization', 'Token ' . $token);
        $version = $this->packageInfo->getVersion(self::MODULE);
        $curl->addHeader('User-Agent', self::USER_AGENT . ($version !== '' ? '/' . $version : ''));

        return $curl;
    }
}
