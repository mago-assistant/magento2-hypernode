<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Skills\Hosting;

use MagoAssistant\Hypernode\Service\Config;
use MagoAssistant\Mago\Service\Skills\AbstractSkill;

class HypernodeStatus extends AbstractSkill
{
    public function getName(): string
    {
        return 'hypernode_status';
    }

    protected function getBaseDescription(): string
    {
        return 'Server performance of the Hypernode this store runs on: load, memory, disk, PHP-FPM workers, '
            . 'HTTP traffic and errors from the nginx log, node services and settings such as MailHog and Varnish, '
            . 'recent node tasks and Hypernode Insights annotations.';
    }

    public function getMagentoAcl(array $input = []): string
    {
        return 'Magento_Backend::system';
    }

    protected function getBaseInstructions(): string
    {
        return 'Use this skill when the admin asks whether the site or server is slow, down, under attack or '
            . 'out of resources, or which services run on the node ("node_settings"). For slowness start with '
            . '"server_overview", then "php_workers" and "http_traffic" to find the cause. Rules of thumb: '
            . 'load_per_cpu_percent above 100 means the CPUs are saturated; '
            . 'PHP-FPM usage_percent above 80 means requests are queueing; a 5xx error_rate_percent above 1 '
            . 'is a problem; a low varnish share in handlers means the full page cache is not helping. '
            . 'Server metrics are live, not history: for graphs over time send the admin to Hypernode Insights '
            . 'at ' . Config::INSIGHTS_URL . '. When a result contains "error", the Hypernode API is not '
            . 'configured or unreachable; tell the admin how to fix it and use what the other actions still '
            . 'return. Show numbers as stat cards or meters where that helps.';
    }
}
