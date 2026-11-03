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

/**
 * The node's hypernode-systemctl settings. Hypernode's own list of settings and allowed values decides
 * what is shown, so the app's secrets, IPs and contact details never leave this class.
 */
class NodeSettingsAction implements ActionInterface
{
    public const DOCS_URL = 'https://docs.hypernode.com/hypernode-platform/tools/how-to-use-the-hypernode-systemctl-cli-tool.html';

    // App fields that are not systemctl settings but give the settings their meaning.
    private const CONTEXT = ['in_production'];
    private const MAX_REQUESTED = 20;

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
        return 'Current hypernode-systemctl settings of the node, such as MailHog, Varnish, Redis/Valkey, '
            . 'OpenSearch, RabbitMQ, PHP/MySQL/Node.js versions and profilers; name settings to also get '
            . 'the values they can be set to';
    }

    public function getParameterSchema(): array
    {
        return [
            'settings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Setting names, e.g. ["php_version"], to also return their allowed values; '
                    . 'omit for all current values',
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
        // Keys are Hypernode's setting names, and a value is only emitted when it is one of the choices
        // Hypernode lists for that setting, a boolean or a number: no free text can pass.
        return [PiiClass::ANY => [PiiClass::PUBLIC]];
    }

    public function getInstructions(): string
    {
        return 'Setting names are those of hypernode-systemctl and describe themselves; ask for specific '
            . '"settings" to see what they can be changed to, e.g. which PHP versions the node supports. '
            . 'in_production tells whether this is the live shop: MailHog enabled there means customers get '
            . 'no email. This skill cannot change settings: the admin does that in the Hypernode Control '
            . 'Panel or with "hypernode-systemctl settings <name> <value>" on the node, see ' . self::DOCS_URL . '.';
    }

    public function execute(array $params, int $adminUserId): array
    {
        try {
            $descriptions = $this->apiClient->getSettingDescriptions();
            $app = $this->apiClient->getApp();
        } catch (ApiException $e) {
            return ['error' => $e->getMessage()];
        }

        $requested = array_slice(array_filter((array)($params['settings'] ?? []), 'is_string'), 0, self::MAX_REQUESTED);
        if ($requested !== []) {
            return $this->describe($requested, $descriptions, $app);
        }

        $settings = [];
        foreach ($descriptions as $name => $options) {
            if (is_string($name) && array_key_exists($name, $app) && $this->isSafe($app[$name], $options)) {
                $settings[$name] = $app[$name];
            }
        }
        ksort($settings);

        return ['context' => $this->context($app), 'settings' => $settings];
    }

    /**
     * @param string[] $names
     */
    private function describe(array $names, array $descriptions, array $app): array
    {
        $settings = [];
        $unknown = [];
        foreach ($names as $name) {
            if (!array_key_exists($name, $descriptions)) {
                $unknown[] = $name;
                continue;
            }
            $options = $descriptions[$name];
            $value = $app[$name] ?? null;
            $settings[$name] = [
                'value' => $this->isSafe($value, $options) ? $value : null,
                'options' => is_array($options) ? array_values(array_filter($options, 'is_scalar')) : [],
            ];
        }

        $result = ['context' => $this->context($app), 'settings' => $settings];
        if ($unknown !== []) {
            $result['unknown_settings'] = $unknown;
        }

        return $result;
    }

    private function context(array $app): array
    {
        $context = [];
        foreach (self::CONTEXT as $field) {
            if (is_bool($app[$field] ?? null)) {
                $context[$field] = $app[$field];
            }
        }

        return $context;
    }

    private function isSafe(mixed $value, mixed $options): bool
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }
        if (!is_string($value) || !is_array($options)) {
            return false;
        }

        return in_array($value, array_map('strval', array_filter($options, 'is_scalar')), true);
    }
}
