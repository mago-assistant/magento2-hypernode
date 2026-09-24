<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Node;

use MagoAssistant\Hypernode\Service\Config;
use Magento\Framework\Filesystem\Driver\File as FileDriver;

/**
 * Aggregates the tail of Hypernode's JSON nginx access log over a time window. Only the last
 * MAX_BYTES of the file are read, and no IP, user or query string leaves this class.
 */
class AccessLogReader
{
    private const MAX_BYTES = 32 * 1024 * 1024;
    private const CHUNK = 1024 * 1024;

    public function __construct(
        private readonly FileDriver $fileDriver,
        private readonly Config $config
    ) {
    }

    /**
     * @throws \RuntimeException when the log cannot be read
     */
    public function summarize(int $minutes, ?\DateTimeImmutable $now = null): array
    {
        $path = $this->config->getAccessLogPath();
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $since = $now->getTimestamp() - $minutes * 60;

        $stats = new AccessLogStats();
        $truncated = false;
        foreach ($this->tailLines($path, $truncated) as $line) {
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                continue;
            }
            $time = strtotime((string)($entry['time'] ?? ''));
            if ($time === false || $time < $since || $time > $now->getTimestamp()) {
                continue;
            }
            $stats->add($entry, $time);
        }

        return $stats->toArray($minutes, $path, $truncated);
    }

    /**
     * The complete lines within the last MAX_BYTES of the file
     *
     * @return iterable<string>
     */
    private function tailLines(string $path, bool &$truncated): iterable
    {
        if (!$this->fileDriver->isReadable($path)) {
            throw new \RuntimeException('The access log ' . $path . ' is not readable from PHP.');
        }

        $size = (int)($this->fileDriver->stat($path)['size'] ?? 0);
        $offset = max(0, $size - self::MAX_BYTES);
        $truncated = $offset > 0;

        $handle = $this->fileDriver->fileOpen($path, 'r');
        try {
            if ($offset > 0) {
                $this->fileDriver->fileSeek($handle, $offset);
                $this->fileDriver->fileReadLine($handle, self::CHUNK); // drop the partial first line
            }
            $remainder = '';
            while (($chunk = $this->fileDriver->fileRead($handle, self::CHUNK)) !== '') {
                $remainder .= $chunk;
                $lines = explode("\n", $remainder);
                $remainder = (string)array_pop($lines);
                foreach ($lines as $line) {
                    if ($line !== '') {
                        yield $line;
                    }
                }
            }
            if (trim($remainder) !== '') {
                yield $remainder;
            }
        } finally {
            $this->fileDriver->fileClose($handle);
        }
    }
}
