<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Node;

/**
 * Reduces a logged request URI to a path safe to show the model: no query string, no secret or token
 * segments, and no room for a visitor to spell out instructions (odd characters, deep or long paths).
 */
class PathNormalizer
{
    private const MAX_SEGMENTS = 8;
    private const MAX_SEGMENT_LENGTH = 40;

    public function normalize(string $uri): string
    {
        $end = strpos($uri, '?');
        $path = $end === false ? $uri : substr($uri, 0, $end);

        $path = (string)preg_replace('#/key/[A-Za-z0-9_-]+#', '/key/*', $path);
        $path = (string)preg_replace('#/[A-Za-z0-9_-]{24,}(?=/|$)#', '/*', $path);

        $trailingSlash = str_ends_with($path, '/') && $path !== '/';
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
        $segments = array_map(
            static fn (string $s): string => strlen($s) <= self::MAX_SEGMENT_LENGTH
                && preg_match('/^[A-Za-z0-9._~*-]+$/', $s) ? $s : '*',
            $segments
        );
        if (count($segments) > self::MAX_SEGMENTS) {
            return '/' . implode('/', array_slice($segments, 0, self::MAX_SEGMENTS)) . '/...';
        }

        return '/' . implode('/', $segments) . ($trailingSlash ? '/' : '');
    }
}
