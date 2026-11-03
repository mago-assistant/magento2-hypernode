<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Node;

/**
 * Turns hypernode-fpm-status output (one worker per line) into a worker summary without the
 * client IPs the lines carry.
 */
class FpmStatusParser
{
    private readonly PathNormalizer $paths;
    private readonly UserAgentClassifier $userAgents;

    private const MAX_ACTIVE_REQUESTS = 10;
    private const HTTP_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'PURGE', 'BAN'];

    public function __construct(?PathNormalizer $paths = null, ?UserAgentClassifier $userAgents = null)
    {
        $this->paths = $paths ?? new PathNormalizer();
        $this->userAgents = $userAgents ?? new UserAgentClassifier();
    }

    /**
     * @return array{
     *     total_workers: int, idle_workers: int, active_workers: int, usage_percent: int,
     *     longest_running_seconds: float, active_requests: array<int, array<string, mixed>>
     * }
     */
    public function parse(string $output): array
    {
        $total = 0;
        $idle = 0;
        $active = [];

        foreach (preg_split('/\r?\n/', trim($output)) ?: [] as $line) {
            $tokens = preg_split('/\s+/', trim($line)) ?: [];
            if (count($tokens) < 3 || !ctype_digit($tokens[0])) {
                continue;
            }
            $total++;

            $state = strtoupper($tokens[1]);
            if ($state === 'IDLE') {
                $idle++;
                continue;
            }

            $active[] = $this->describeRequest($tokens, $line, $state);
        }

        usort($active, static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds']);

        return [
            'total_workers' => $total,
            'idle_workers' => $idle,
            'active_workers' => count($active),
            'usage_percent' => $total > 0 ? (int)round(count($active) / $total * 100) : 0,
            'longest_running_seconds' => $active[0]['seconds'] ?? 0.0,
            'active_requests' => array_slice($active, 0, self::MAX_ACTIVE_REQUESTS),
        ];
    }

    /**
     * @param string[] $tokens
     * @return array{state: string, seconds: float, method: string, path: string, user_agent: string}
     */
    private function describeRequest(array $tokens, string $line, string $state): array
    {
        $method = '';
        $path = '';
        foreach ($tokens as $index => $token) {
            if (in_array($token, self::HTTP_METHODS, true)) {
                $method = $token;
                $path = $this->paths->normalize((string)($tokens[$index + 1] ?? ''));
                break;
            }
        }

        $userAgent = '';
        if (preg_match('/\(([^()]*)\)\s*$/', $line, $matches)) {
            $userAgent = $this->userAgents->classify($matches[1]);
        }

        return [
            'state' => $state,
            'seconds' => (float)rtrim($tokens[2], 's'),
            'method' => $method,
            'path' => $path,
            'user_agent' => $userAgent,
        ];
    }
}
