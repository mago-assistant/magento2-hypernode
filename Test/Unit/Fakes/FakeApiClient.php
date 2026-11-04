<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Fakes;

use MagoAssistant\Hypernode\Service\Api\ApiClientInterface;
use MagoAssistant\Hypernode\Service\Api\ApiException;

final class FakeApiClient implements ApiClientInterface
{
    private array $app = [];
    private array $settingDescriptions = [];
    private string $fpmStatus = '';
    private array $flows = [];
    private array $annotations = [];
    private ?string $failure = null;

    public function withApp(array $app): self
    {
        $this->app = $app;

        return $this;
    }

    public function withSettingDescriptions(array $descriptions): self
    {
        $this->settingDescriptions = $descriptions;

        return $this;
    }

    public function withFpmStatus(string $output): self
    {
        $this->fpmStatus = $output;

        return $this;
    }

    public function withFlows(array $page): self
    {
        $this->flows = $page;

        return $this;
    }

    public function withAnnotations(array $response): self
    {
        $this->annotations = $response;

        return $this;
    }

    public function failingWith(string $message): self
    {
        $this->failure = $message;

        return $this;
    }

    public function getApp(): array
    {
        $this->failIfAsked();

        return $this->app;
    }

    public function getSettingDescriptions(): array
    {
        $this->failIfAsked();

        return $this->settingDescriptions;
    }

    public function getFpmStatus(): string
    {
        $this->failIfAsked();

        return $this->fpmStatus;
    }

    public function getFlows(): array
    {
        $this->failIfAsked();

        return $this->flows;
    }

    public function listAnnotations(): array
    {
        $this->failIfAsked();

        return $this->annotations;
    }

    private function failIfAsked(): void
    {
        if ($this->failure !== null) {
            throw new ApiException($this->failure);
        }
    }
}
