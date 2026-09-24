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

class ListAnnotationsAction implements ActionInterface
{
    private const MAX = 25;

    public function __construct(
        private readonly ApiClientInterface $apiClient
    ) {
    }

    public function getName(): string
    {
        return 'list_annotations';
    }

    public function getDescription(): string
    {
        return 'Custom Hypernode Insights annotations (deploys, maintenance, incidents) created through the API';
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
            'total' => [PiiClass::PUBLIC],
            'name' => [PiiClass::PUBLIC],
            'at' => [PiiClass::PUBLIC],
            'metrics' => [PiiClass::PUBLIC],
            'note' => [PiiClass::PUBLIC],
        ];
    }

    public function getInstructions(): string
    {
        return 'Correlate the "at" timestamps with slowdowns or error spikes the admin describes: '
            . 'an annotation shortly before a problem is the first suspect.';
    }

    public function execute(array $params, int $adminUserId): array
    {
        try {
            $response = $this->apiClient->listAnnotations();
        } catch (ApiException $e) {
            return ['error' => $e->getMessage()];
        }

        $items = $response['results'] ?? (array_is_list($response) ? $response : []);
        $annotations = [];
        foreach (array_slice($items, 0, self::MAX) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $annotations[] = [
                'name' => (string)($item['name'] ?? ''),
                'at' => (string)($item['x_axis'] ?? ''),
                'metrics' => array_values(array_map('strval', (array)($item['metrics'] ?? []))),
                'note' => (string)($item['metadata']['note'] ?? ''),
            ];
        }

        return [
            'total' => (int)($response['count'] ?? count($items)),
            'annotations' => $annotations,
        ];
    }
}
