<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Mago\Api\Skill\ActionInterface;
use MagoAssistant\Hypernode\Service\Api\ApiClientInterface;
use MagoAssistant\Hypernode\Service\Api\ApiException;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

class NodeSettingsAction implements ActionInterface
{
    // The app response also carries secrets, IPs, SSH keys and the owner's contact details, so only
    // these named settings ever leave this class; a field Hypernode adds later stays out until listed.
    private const SETTINGS = [
        'mail' => ['mailhog_enabled'],
        'cache' => [
            'varnish_enabled',
            'varnish_version',
            'redis_version',
            'redis_persistent_instance',
            'valkey_enabled',
        ],
        'search' => ['opensearch_enabled', 'opensearch_version', 'elasticsearch_enabled', 'elasticsearch_version'],
        'queue' => ['rabbitmq_enabled'],
        'stack' => [
            'php_version',
            'alternative_php_versions',
            'mysql_enabled',
            'mysql_version',
            'mariadb_enabled',
            'mariadb_version',
            'nodejs_version',
            'composer_version',
            'operating_system',
        ],
        'profiling' => ['php_xdebug_enabled', 'blackfire_enabled', 'tideways_enabled', 'new_relic_enabled'],
        'other' => [
            'in_production',
            'phpmyadmin_enabled',
            'auto_scaling_enabled',
            'botstopper_enabled',
            'monitoring_enabled',
        ],
    ];

    public function __construct(
        private readonly ApiClientInterface $apiClient
    ) {
    }

    public function getName(): string
    {
        return 'node_settings';
    }

    public function getDescription(): string
    {
        return 'Services and settings on the node: MailHog, Varnish, Redis/Valkey, OpenSearch/Elasticsearch, '
            . 'RabbitMQ, PHP/MySQL/Node.js versions and profilers such as Xdebug, Blackfire and Tideways';
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
        $rules = [];
        foreach (self::SETTINGS as $fields) {
            foreach ($fields as $field) {
                $rules[$field] = [PiiClass::PUBLIC];
            }
        }

        return $rules;
    }

    public function getInstructions(): string
    {
        return 'MailHog catches every outgoing email instead of delivering it: mailhog_enabled true on a node '
            . 'with in_production true means customers receive no mail. varnish_enabled false means no full '
            . 'page cache in front of PHP. Xdebug enabled slows every request down. Settings are changed in '
            . 'the Hypernode Control Panel or with hypernode-systemctl on the node.';
    }

    public function execute(array $params, int $adminUserId): array
    {
        try {
            $app = $this->apiClient->getApp();
        } catch (ApiException $e) {
            return ['error' => $e->getMessage()];
        }

        $result = [];
        foreach (self::SETTINGS as $group => $fields) {
            foreach ($fields as $field) {
                $value = $app[$field] ?? null;
                if (is_array($value) && array_is_list($value)) {
                    $value = implode(', ', array_filter($value, 'is_scalar'));
                }
                if (array_key_exists($field, $app) && ($value === null || is_scalar($value))) {
                    $result[$group][$field] = $value;
                }
            }
        }

        return $result;
    }
}
