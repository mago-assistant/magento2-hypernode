<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Node;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;

/**
 * Load, memory, uptime and disk of the machine PHP runs on, read from /proc; every value is null
 * where the kernel does not offer it (non-Linux, restricted containers).
 */
class SystemMetrics
{
    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly DirectoryList $directoryList
    ) {
    }

    private const CPU_SAMPLE_MICROSECONDS = 1000000;

    public function collect(): array
    {
        $memory = $this->memory();
        $cpuCount = $this->cpuCount();
        $load = $this->loadAverage();

        return [
            'hostname' => (string)gethostname(),
            'cpu_count' => $cpuCount,
            'cpu_usage_percent' => $this->cpuUsagePercent(),
            'load_1m' => $load[0] ?? null,
            'load_5m' => $load[1] ?? null,
            'load_15m' => $load[2] ?? null,
            'load_per_cpu_percent' => isset($load[0]) && $cpuCount ? (int)round($load[0] / $cpuCount * 100) : null,
            'memory_total_mb' => $memory['total'],
            'memory_available_mb' => $memory['available'],
            'memory_used_percent' => $this->percentUsed($memory['total'], $memory['available']),
            'memory_used_including_cache_percent' => $this->percentUsed($memory['total'], $memory['free']),
            'swap_used_mb' => $memory['swap_used'],
            'uptime_hours' => $this->uptimeHours(),
            'disk' => $this->disk(),
        ];
    }

    private function percentUsed(?int $total, ?int $unused): ?int
    {
        return $total && $unused !== null ? (int)round(($total - $unused) / $total * 100) : null;
    }

    /**
     * Share of CPU time not idle over a one second sample of /proc/stat, the figure Insights graphs
     */
    private function cpuUsagePercent(): ?int
    {
        $before = $this->cpuTimes();
        if ($before === null) {
            return null;
        }
        usleep(self::CPU_SAMPLE_MICROSECONDS);
        $after = $this->cpuTimes();
        if ($after === null) {
            return null;
        }

        $total = $after['total'] - $before['total'];
        $idle = $after['idle'] - $before['idle'];

        return $total > 0 ? (int)round(($total - $idle) / $total * 100) : null;
    }

    /**
     * @return array{total: int, idle: int}|null
     */
    private function cpuTimes(): ?array
    {
        $raw = $this->read('/proc/stat');
        if ($raw === null || !preg_match('/^cpu\s+(.+)$/m', $raw, $matches)) {
            return null;
        }
        $fields = array_map('intval', preg_split('/\s+/', trim($matches[1])) ?: []);
        if (count($fields) < 5) {
            return null;
        }

        // user nice system idle iowait irq softirq steal: idle and iowait both count as not busy
        return ['total' => array_sum(array_slice($fields, 0, 8)), 'idle' => $fields[3] + $fields[4]];
    }

    /**
     * @return float[]
     */
    private function loadAverage(): array
    {
        $raw = $this->read('/proc/loadavg');
        if ($raw === null) {
            return [];
        }
        $parts = preg_split('/\s+/', trim($raw)) ?: [];

        return array_map('floatval', array_slice($parts, 0, 3));
    }

    /**
     * @return array{total: ?int, available: ?int, free: ?int, swap_used: ?int}
     */
    private function memory(): array
    {
        $raw = $this->read('/proc/meminfo');
        if ($raw === null) {
            return ['total' => null, 'available' => null, 'free' => null, 'swap_used' => null];
        }

        $values = [];
        foreach (explode("\n", $raw) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches)) {
                $values[$matches[1]] = (int)round((int)$matches[2] / 1024);
            }
        }

        return [
            'total' => $values['MemTotal'] ?? null,
            'available' => $values['MemAvailable'] ?? null,
            'free' => $values['MemFree'] ?? null,
            'swap_used' => isset($values['SwapTotal'], $values['SwapFree'])
                ? $values['SwapTotal'] - $values['SwapFree']
                : null,
        ];
    }

    private function cpuCount(): ?int
    {
        $raw = $this->read('/proc/cpuinfo');
        if ($raw === null) {
            return null;
        }
        $count = preg_match_all('/^processor\s*:/m', $raw);

        return $count > 0 ? $count : null;
    }

    private function uptimeHours(): ?float
    {
        $raw = $this->read('/proc/uptime');
        if ($raw === null) {
            return null;
        }

        return round((float)strtok(trim($raw), ' ') / 3600, 1);
    }

    /**
     * @return array{path: string, total_gb: float, free_gb: float, used_percent: int}|null
     */
    private function disk(): ?array
    {
        try {
            $path = $this->directoryList->getRoot();
            $total = disk_total_space($path);
            $free = disk_free_space($path);
        } catch (\Throwable $e) {
            return null;
        }
        if ($total === false || $free === false || $total <= 0) {
            return null;
        }

        return [
            'path' => $path,
            'total_gb' => round($total / 1024 ** 3, 1),
            'free_gb' => round($free / 1024 ** 3, 1),
            'used_percent' => (int)round(($total - $free) / $total * 100),
        ];
    }

    private function read(string $path): ?string
    {
        try {
            if (!$this->fileDriver->isReadable($path)) {
                return null;
            }

            return $this->fileDriver->fileGetContents($path);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
