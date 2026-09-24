<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Node;

/**
 * Running totals for one AccessLogReader pass
 */
class AccessLogStats
{
    private const TOP = 10;
    private readonly PathNormalizer $normalizer;
    private const STATIC_EXTENSIONS = ['js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'woff', 'woff2', 'ico'];

    private int $requests = 0;
    /** @var array<string, int> */
    private array $statusClasses = ['2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0];
    /** @var array<int, int> */
    private array $statuses = [];
    /** @var array<string, int> */
    private array $handlers = [];
    /** @var array<string, int> */
    private array $paths = [];
    /** @var array<string, int> */
    private array $errorPaths = [];
    /** @var array<string, int> */
    private array $countries = [];
    /** @var array<string, int> */
    private array $userAgents = [];
    /** @var array<string, true> */
    private array $clients = [];
    /** @var float[] */
    private array $phpTimes = [];
    private int $firstSeen = 0;
    private int $lastSeen = 0;
    private int $staticRequests = 0;

    public function __construct(?PathNormalizer $paths = null)
    {
        $this->normalizer = $paths ?? new PathNormalizer();
    }

    public function add(array $entry, int $time): void
    {
        $this->requests++;
        $this->firstSeen = $this->firstSeen === 0 ? $time : min($this->firstSeen, $time);
        $this->lastSeen = max($this->lastSeen, $time);

        $status = (int)($entry['status'] ?? 0);
        $class = intdiv($status, 100) . 'xx';
        if (isset($this->statusClasses[$class])) {
            $this->statusClasses[$class]++;
        }
        $this->statuses[$status] = ($this->statuses[$status] ?? 0) + 1;

        $handler = (string)($entry['handler'] ?? 'unknown');
        $this->handlers[$handler] = ($this->handlers[$handler] ?? 0) + 1;

        $path = $this->pathOf((string)($entry['request'] ?? ''));
        if ($this->isStatic($path)) {
            $this->staticRequests++;
        } else {
            $this->paths[$path] = ($this->paths[$path] ?? 0) + 1;
            if ($status >= 500) {
                $this->errorPaths[$path] = ($this->errorPaths[$path] ?? 0) + 1;
            }
        }

        $country = (string)($entry['country'] ?? '');
        if ($country !== '' && $country !== '-') {
            $this->countries[$country] = ($this->countries[$country] ?? 0) + 1;
        }

        $userAgent = mb_substr((string)($entry['user_agent'] ?? ''), 0, 80);
        if ($userAgent !== '') {
            $this->userAgents[$userAgent] = ($this->userAgents[$userAgent] ?? 0) + 1;
        }

        $client = (string)($entry['remote_addr'] ?? '');
        if ($client !== '') {
            $this->clients[hash('sha256', $client)] = true;
        }

        if ($handler === 'phpfpm' && isset($entry['request_time'])) {
            $this->phpTimes[] = (float)$entry['request_time'];
        }
    }

    public function toArray(int $minutes, string $path, bool $truncated): array
    {
        $top = self::TOP;
        $covered = $this->requests > 0 ? max(1, (int)ceil(($this->lastSeen - $this->firstSeen) / 60)) : $minutes;
        $errors5xx = $this->statusClasses['5xx'];

        return [
            'window_minutes' => $minutes,
            'log_path' => $path,
            'log_truncated_to_tail' => $truncated,
            'requests' => $this->requests,
            'requests_per_minute' => $this->requests > 0 ? round($this->requests / $covered, 1) : 0.0,
            'static_requests' => $this->staticRequests,
            'distinct_clients' => count($this->clients),
            'status_classes' => $this->statusClasses,
            'error_rate_percent' => $this->requests > 0 ? round($errors5xx / $this->requests * 100, 2) : 0.0,
            'top_statuses' => $this->top($this->statuses, $top),
            'handlers' => $this->top($this->handlers, $top),
            'php_response_time' => $this->responseTimes(),
            'top_paths' => $this->top($this->paths, $top),
            'top_5xx_paths' => $this->top($this->errorPaths, $top),
            'top_countries' => $this->top($this->countries, $top),
            'top_user_agents' => $this->top($this->userAgents, 5),
        ];
    }

    private function responseTimes(): ?array
    {
        if ($this->phpTimes === []) {
            return null;
        }
        $times = $this->phpTimes;
        sort($times);
        $count = count($times);

        return [
            'requests' => $count,
            'avg_seconds' => round(array_sum($times) / $count, 3),
            'p95_seconds' => round($times[(int)min($count - 1, floor($count * 0.95))], 3),
            'max_seconds' => round($times[$count - 1], 3),
        ];
    }

    /**
     * @param array<int|string, int> $counts
     * @return array<int, array{value: string, count: int}>
     */
    private function top(array $counts, int $limit): array
    {
        arsort($counts);
        $result = [];
        foreach (array_slice($counts, 0, $limit, true) as $value => $count) {
            $result[] = ['value' => (string)$value, 'count' => $count];
        }

        return $result;
    }

    private function pathOf(string $request): string
    {
        $parts = explode(' ', $request);

        return $this->normalizer->normalize($parts[1] ?? $parts[0]);
    }

    private function isStatic(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, self::STATIC_EXTENSIONS, true);
    }
}
