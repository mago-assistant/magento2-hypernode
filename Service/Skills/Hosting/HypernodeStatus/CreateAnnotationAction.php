<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Mago\Api\Skill\ValidatingActionInterface;
use MagoAssistant\Hypernode\Service\Api\ApiClientInterface;
use MagoAssistant\Hypernode\Service\Api\ApiException;
use MagoAssistant\Hypernode\Service\Config;
use MagoAssistant\Mago\Service\Privacy\PiiClass;

class CreateAnnotationAction implements ValidatingActionInterface
{
    private const MAX_NAME_LENGTH = 120;

    public function __construct(
        private readonly ApiClientInterface $apiClient
    ) {
    }

    public function getName(): string
    {
        return 'create_annotation';
    }

    public function getDescription(): string
    {
        return 'Mark this moment in the Hypernode Insights graphs with a named annotation, e.g. a deploy, '
            . 'a cache flush or the start of an incident';
    }

    public function getParameterSchema(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'Short label shown on the Insights timeline, e.g. "Cache flushed after price import"',
            ],
            'metrics' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional Insights metric names the annotation applies to; omit for all metrics',
            ],
            'note' => [
                'type' => 'string',
                'description' => 'Optional longer note stored with the annotation',
            ],
        ];
    }

    public function getAclResource(): ?string
    {
        return null;
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getFieldClassification(): array
    {
        return [
            'created' => [PiiClass::PUBLIC],
            'name' => [PiiClass::PUBLIC],
            'at' => [PiiClass::PUBLIC],
            'insights_url' => [PiiClass::PUBLIC],
        ];
    }

    public function getInstructions(): string
    {
        return 'Annotations cannot be removed through the API, so use them for real events only. The '
            . 'annotation is placed at the current time.';
    }

    public function findRefusal(array $params): ?array
    {
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'An annotation needs a name.'];
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            return ['error' => 'The annotation name is longer than ' . self::MAX_NAME_LENGTH . ' characters.'];
        }

        return null;
    }

    public function execute(array $params, int $adminUserId): array
    {
        $refusal = $this->findRefusal($params);
        if ($refusal !== null) {
            return $refusal;
        }

        $name = trim((string)$params['name']);
        $metrics = [];
        foreach ((array)($params['metrics'] ?? []) as $metric) {
            if (is_scalar($metric) && trim((string)$metric) !== '') {
                $metrics[] = mb_substr(trim((string)$metric), 0, 64);
            }
        }
        $metadata = ['source' => 'mago'];
        $note = trim((string)($params['note'] ?? ''));
        if ($note !== '') {
            $metadata['note'] = $note;
        }
        $at = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        try {
            $this->apiClient->createAnnotation($name, $at, $metrics, $metadata);
        } catch (ApiException $e) {
            return ['error' => $e->getMessage()];
        }

        return [
            'created' => true,
            'name' => $name,
            'at' => $at->format(\DateTimeInterface::ATOM),
            'insights_url' => Config::INSIGHTS_URL,
        ];
    }
}
