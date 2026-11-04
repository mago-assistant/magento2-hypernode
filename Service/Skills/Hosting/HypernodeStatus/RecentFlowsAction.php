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

class RecentFlowsAction implements ActionInterface
{
    private const DEFAULT_LIMIT = 10;
    private const MAX_LIMIT = 50;

    public function __construct(
        private readonly ApiClientInterface $apiClient
    ) {
    }

    public function getName(): string
    {
        return 'recent_flows';
    }

    public function getDescription(): string
    {
        return 'Recent Hypernode tasks (flows) on the node: settings changes, backups, updates, with their state';
    }

    public function getParameterSchema(): array
    {
        return [
            'limit' => [
                'type' => 'integer',
                'description' => 'Number of flows to return, default 10, max 50',
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
            'total' => [PiiClass::PUBLIC],
            'running' => [PiiClass::PUBLIC],
            'name' => [PiiClass::PUBLIC],
            'state' => [PiiClass::PUBLIC],
            'created_at' => [PiiClass::PUBLIC],
            'updated_at' => [PiiClass::PUBLIC],
            'completed_steps' => [PiiClass::PUBLIC],
            'total_steps' => [PiiClass::PUBLIC],
            'description' => [PiiClass::PUBLIC],
        ];
    }

    public function getInstructions(): string
    {
        return 'A flow with state null or "running" is still in progress; "update_node" applies a settings '
            . 'change and briefly restarts services, which explains short hiccups around created_at.';
    }

    public function execute(array $params, int $adminUserId): array
    {
        $limit = max(1, min(self::MAX_LIMIT, (int)($params['limit'] ?? self::DEFAULT_LIMIT)));

        try {
            $page = $this->apiClient->getFlows();
        } catch (ApiException $e) {
            return ['error' => $e->getMessage()];
        }

        $flows = [];
        $running = 0;
        foreach (array_slice($page['results'] ?? [], 0, $limit) as $flow) {
            $state = $flow['state'] ?? null;
            if ($state === null || $state === 'running') {
                $running++;
            }
            $flows[] = [
                'name' => (string)($flow['name'] ?? ''),
                'state' => $state === null ? 'running' : (string)$state,
                'created_at' => (string)($flow['created_at'] ?? ''),
                'updated_at' => (string)($flow['updated_at'] ?? ''),
                'completed_steps' => (int)($flow['progress']['completed'] ?? 0),
                'total_steps' => (int)($flow['progress']['total'] ?? 0),
                'description' => (string)($flow['tracker']['description'] ?? ''),
            ];
        }

        return [
            'total' => (int)($page['count'] ?? count($flows)),
            'running' => $running,
            'flows' => $flows,
        ];
    }
}
