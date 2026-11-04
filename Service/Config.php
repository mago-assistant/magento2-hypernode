<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service;

use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * App name and API token come from /etc/hypernode/app.json, which every Hypernode carries.
 */
class Config
{
    public const API_URL = 'https://api.hypernode.com';
    public const INSIGHTS_URL = 'https://insights.hypernode.com';
    public const APP_FILE = '/etc/hypernode/app.json';
    public const ACCESS_LOG_PATH = '/var/log/nginx/access.log';

    /** @var array<string, mixed>|null */
    private ?array $app = null;

    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly Json $json
    ) {
    }

    /**
     * The app part of hn_fqdn ("yourshop" for yourshop.hypernode.io, also right on a Brancher node), else ''
     */
    public function getAppName(): string
    {
        $fqdn = (string)($this->app()['hn_fqdn'] ?? '');
        $domain = (string)($this->app()['hn_domain'] ?? 'hypernode.io');
        $suffix = '.' . $domain;

        return str_ends_with($fqdn, $suffix) ? substr($fqdn, 0, -strlen($suffix)) : '';
    }

    public function getApiToken(): string
    {
        return trim((string)($this->app()['hypernode_api_token'] ?? ''));
    }

    public function getAccessLogPath(): string
    {
        return self::ACCESS_LOG_PATH;
    }

    /**
     * Whether PHP runs on the Hypernode itself, so /proc and the access log describe the node
     */
    public function isOnHypernode(): bool
    {
        return $this->app() !== [];
    }

    public function isApiConfigured(): bool
    {
        return $this->getAppName() !== '' && $this->getApiToken() !== '';
    }

    /**
     * What the on-node actions answer with when PHP does not run on the Hypernode
     */
    public function getNotOnNodeMessage(): string
    {
        return 'Magento does not run on the Hypernode itself, so live load, CPU, memory, disk and the access '
            . 'log are not available here; see Hypernode Insights at ' . self::INSIGHTS_URL . ' for them.';
    }

    /**
     * The one sentence every action answers with when the API cannot be used
     */
    public function getNotConfiguredMessage(): string
    {
        return 'The Hypernode API is only available when Magento runs on the Hypernode itself: the app name '
            . 'and API token are read from ' . self::APP_FILE . ', which was not found or is incomplete here.';
    }

    /**
     * @return array<string, mixed> The decoded app.json, or [] off the node or when it cannot be read
     */
    private function app(): array
    {
        if ($this->app !== null) {
            return $this->app;
        }

        $this->app = [];
        try {
            if ($this->fileDriver->isReadable(self::APP_FILE)) {
                $decoded = $this->json->unserialize($this->fileDriver->fileGetContents(self::APP_FILE));
                $this->app = is_array($decoded) ? $decoded : [];
            }
        } catch (\Throwable $e) {
            $this->app = [];
        }

        return $this->app;
    }
}
