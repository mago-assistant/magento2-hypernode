<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Mago\Api\Skill\ActionInterface;
use MagoAssistant\Hypernode\Service\Node\AccessLogReader;
use MagoAssistant\Hypernode\Service\Config;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

class HttpTrafficAction implements ActionInterface
{
    private const DEFAULT_MINUTES = 15;
    private const MAX_MINUTES = 1440;

    public function __construct(
        private readonly AccessLogReader $accessLogReader,
        private readonly Config $config
    ) {
    }

    public function getName(): string
    {
        return 'http_traffic';
    }

    public function getDescription(): string
    {
        return 'HTTP traffic from the nginx access log over the last N minutes: request rate, status codes, '
            . '5xx errors, cache handlers, PHP response times, top paths, countries and user agents';
    }

    public function getParameterSchema(): array
    {
        return [
            'minutes' => [
                'type' => 'integer',
                'description' => 'Window in minutes to summarize, default 15, max 1440',
            ],
        ];
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
            'window_minutes' => [PiiClass::PUBLIC],
            'log_path' => [PiiClass::PUBLIC],
            'log_truncated_to_tail' => [PiiClass::PUBLIC],
            'requests' => [PiiClass::PUBLIC],
            'requests_per_minute' => [PiiClass::PUBLIC],
            'static_requests' => [PiiClass::PUBLIC],
            'distinct_clients' => [PiiClass::PUBLIC],
            '2xx' => [PiiClass::PUBLIC],
            '3xx' => [PiiClass::PUBLIC],
            '4xx' => [PiiClass::PUBLIC],
            '5xx' => [PiiClass::PUBLIC],
            'error_rate_percent' => [PiiClass::PUBLIC],
            'value' => [PiiClass::PUBLIC],
            'count' => [PiiClass::PUBLIC],
            'avg_seconds' => [PiiClass::PUBLIC],
            'p95_seconds' => [PiiClass::PUBLIC],
            'max_seconds' => [PiiClass::PUBLIC],
        ];
    }

    public function getInstructions(): string
    {
        return 'handlers tells who answered: "varnish" hits never reached PHP, "phpfpm" did, others are '
            . 'static files or redirects. Many requests from one user agent or country with 4xx statuses '
            . 'suggest a bot; top_5xx_paths shows which pages fail. Query strings and client addresses are '
            . 'never included; distinct_clients is a count only. When log_truncated_to_tail is true the '
            . 'window may be incomplete because only the last part of a large log was read.';
    }

    public function execute(array $params, int $adminUserId): array
    {
        $minutes = (int)($params['minutes'] ?? self::DEFAULT_MINUTES);
        $minutes = max(1, min(self::MAX_MINUTES, $minutes));

        if (!$this->config->isOnHypernode()) {
            return ['error' => $this->config->getNotOnNodeMessage()];
        }

        try {
            return $this->accessLogReader->summarize($minutes);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
