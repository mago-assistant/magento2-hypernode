<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Mago\Api\Skill\ActionInterface;
use MagoAssistant\Hypernode\Service\Api\ApiClientInterface;
use MagoAssistant\Hypernode\Service\Api\ApiException;
use MagoAssistant\Hypernode\Service\Node\FpmStatusParser;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

class PhpWorkersAction implements ActionInterface
{
    public function __construct(
        private readonly ApiClientInterface $apiClient,
        private readonly FpmStatusParser $parser
    ) {
    }

    public function getName(): string
    {
        return 'php_workers';
    }

    public function getDescription(): string
    {
        return 'Live PHP-FPM worker status: how many workers are busy, and the longest running requests';
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
            'total_workers' => [PiiClass::PUBLIC],
            'idle_workers' => [PiiClass::PUBLIC],
            'active_workers' => [PiiClass::PUBLIC],
            'usage_percent' => [PiiClass::PUBLIC],
            'longest_running_seconds' => [PiiClass::PUBLIC],
            'state' => [PiiClass::PUBLIC],
            'seconds' => [PiiClass::PUBLIC],
            'method' => [PiiClass::PUBLIC],
            'path' => [PiiClass::PUBLIC],
            'user_agent' => [PiiClass::PUBLIC],
        ];
    }

    public function getInstructions(): string
    {
        return 'The Hypernode heartbeat requests to magweb/status.php are normal and never a problem. '
            . 'Many active workers on the same path with a high "seconds" value point at a slow page or a '
            . 'crawler; a usage_percent near 100 means visitors are waiting for a free worker.';
    }

    public function execute(array $params, int $adminUserId): array
    {
        try {
            $output = $this->apiClient->getFpmStatus();
        } catch (ApiException $e) {
            return ['error' => $e->getMessage()];
        }

        return $this->parser->parse($output);
    }
}
