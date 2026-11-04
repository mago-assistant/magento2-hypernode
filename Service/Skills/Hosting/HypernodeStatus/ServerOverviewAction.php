<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Mago\Api\Skill\ActionInterface;
use MagoAssistant\Hypernode\Service\Api\ApiClientInterface;
use MagoAssistant\Hypernode\Service\Api\ApiException;
use MagoAssistant\Hypernode\Service\Config;
use MagoAssistant\Hypernode\Service\Node\SystemMetrics;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

class ServerOverviewAction implements ActionInterface
{
    public function __construct(
        private readonly SystemMetrics $systemMetrics,
        private readonly ApiClientInterface $apiClient,
        private readonly Config $config
    ) {
    }

    public function getName(): string
    {
        return 'server_overview';
    }

    public function getDescription(): string
    {
        return 'Live load, memory, swap, uptime and disk of the server, plus the Hypernode plan, PHP and '
            . 'MySQL version';
    }

    public function getParameterSchema(): array
    {
        return [];
    }

    public function getAclResource(): ?string
    {
        return null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getFieldClassification(): array
    {
        return [
            'hostname' => [PiiClass::PUBLIC],
            'cpu_count' => [PiiClass::PUBLIC],
            'cpu_usage_percent' => [PiiClass::PUBLIC],
            'server_note' => [PiiClass::PUBLIC],
            'load_1m' => [PiiClass::PUBLIC],
            'load_5m' => [PiiClass::PUBLIC],
            'load_15m' => [PiiClass::PUBLIC],
            'load_per_cpu_percent' => [PiiClass::PUBLIC],
            'memory_total_mb' => [PiiClass::PUBLIC],
            'memory_available_mb' => [PiiClass::PUBLIC],
            'memory_used_percent' => [PiiClass::PUBLIC],
            'memory_used_including_cache_percent' => [PiiClass::PUBLIC],
            'swap_used_mb' => [PiiClass::PUBLIC],
            'uptime_hours' => [PiiClass::PUBLIC],
            'path' => [PiiClass::PUBLIC],
            'total_gb' => [PiiClass::PUBLIC],
            'free_gb' => [PiiClass::PUBLIC],
            'used_percent' => [PiiClass::PUBLIC],
            'app_name' => [PiiClass::PUBLIC],
            'domainname' => [PiiClass::PUBLIC],
            'plan' => [PiiClass::PUBLIC],
            'redis_size_mb' => [PiiClass::PUBLIC],
            'php_version' => [PiiClass::PUBLIC],
            'mysql_version' => [PiiClass::PUBLIC],
            'type' => [PiiClass::PUBLIC],
            'in_production' => [PiiClass::PUBLIC],
            'hypernode_api' => [PiiClass::PUBLIC],
            'insights_url' => [PiiClass::PUBLIC],
        ];
    }

    public function getInstructions(): string
    {
        return 'cpu_usage_percent is the busy share of all CPUs over a one second sample and matches the CPU '
            . 'graph in Insights; load_per_cpu_percent is the 1-minute load average divided by the CPU count '
            . 'and also counts processes waiting for disk. memory_used_percent excludes page cache '
            . '(memory Linux frees on demand); memory_used_including_cache_percent is the higher figure some '
            . 'dashboards show. All server values are a snapshot of this moment, Insights shows averages over time. '
            . 'Growing swap_used_mb with high memory_used_percent means the plan is too small for the workload. '
            . 'hypernode_api says whether the plan details came from the Hypernode API; the server block is '
            . 'only reported when the API is configured, so the numbers describe the Hypernode itself.';
    }

    public function execute(array $params, int $adminUserId): array
    {
        // Off a Hypernode the /proc values describe some other machine (a laptop running Docker), so
        // without the API there is nothing truthful to report.
        if (!$this->config->isApiConfigured()) {
            return ['error' => $this->config->getNotConfiguredMessage()];
        }

        // Off the node, /proc describes whatever machine runs Magento (a laptop, a CI runner), not the
        // Hypernode, so the live block is left out and Insights is the place for those numbers.
        $result = $this->config->isOnHypernode()
            ? ['server' => $this->systemMetrics->collect()]
            : ['server_note' => $this->config->getNotOnNodeMessage()];

        try {
            $app = $this->apiClient->getApp();
            $result['hypernode'] = [
                'app_name' => (string)($app['name'] ?? $this->config->getAppName()),
                'domainname' => (string)($app['domainname'] ?? ''),
                'plan' => (string)($app['flavor']['name'] ?? ''),
                'redis_size_mb' => isset($app['flavor']['redis_size']) ? (int)$app['flavor']['redis_size'] : null,
                'php_version' => (string)($app['php_version'] ?? ''),
                'mysql_version' => (string)($app['mysql_version'] ?? ''),
                'type' => (string)($app['type'] ?? ''),
                'in_production' => isset($app['in_production']) ? (bool)$app['in_production'] : null,
            ];
            $result['hypernode_api'] = 'ok';
        } catch (ApiException $e) {
            $result['hypernode_api'] = 'unavailable: ' . $e->getMessage();
        }

        $result['insights_url'] = Config::INSIGHTS_URL;

        return $result;
    }
}
