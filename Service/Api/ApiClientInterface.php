<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Api;

/**
 * The Hypernode API calls the hosting skill needs; one method per endpoint, decoded JSON out.
 */
interface ApiClientInterface
{
    /**
     * GET /v2/app/<app>/ — plan, PHP and MySQL version, IP, domain
     *
     * @throws ApiException
     */
    public function getApp(): array;

    /**
     * POST /v2/nats/<app>/hypernode.show-fpm-status — one line per PHP-FPM worker, as text
     *
     * @throws ApiException
     */
    public function getFpmStatus(): string;

    /**
     * GET /logbook/v1/logbooks/<app>/flows/ — first page of node tasks, newest first
     *
     * @throws ApiException
     */
    public function getFlows(): array;

    /**
     * GET /v2/insights-annotation/ — custom Insights annotations
     *
     * @throws ApiException
     */
    public function listAnnotations(): array;

    /**
     * POST /v2/insights-annotation/create/
     *
     * @param string[] $metrics Metric names the annotation applies to, [] for all
     * @throws ApiException
     */
    public function createAnnotation(string $name, \DateTimeInterface $at, array $metrics, array $metadata): array;
}
