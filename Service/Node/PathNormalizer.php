<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Node;

/**
 * Reduces a logged request URI to a path safe to show the model: no query string, no admin secret
 * key segment, no long opaque tokens (password reset, session or download keys).
 */
class PathNormalizer
{
    public function normalize(string $uri): string
    {
        $end = strpos($uri, '?');
        $path = $end === false ? $uri : substr($uri, 0, $end);

        $path = (string)preg_replace('#/key/[A-Za-z0-9_-]+#', '/key/*', $path);

        return (string)preg_replace('#/[A-Za-z0-9_-]{24,}(?=/|$)#', '/*', $path);
    }
}
