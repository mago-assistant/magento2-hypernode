<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Service\Node;

/**
 * Maps a raw user agent to a fixed label, so text a visitor puts in the header never reaches the model.
 */
class UserAgentClassifier
{
    public const OTHER_BOT = 'other bot';
    public const OTHER = 'other';

    // First match wins: bots and tools before browsers, because they often embed browser tokens.
    private const LABELS = [
        'Googlebot' => 'Googlebot',
        'Google-InspectionTool' => 'Googlebot',
        'AdsBot-Google' => 'Googlebot',
        'bingbot' => 'Bingbot',
        'Applebot' => 'Applebot',
        'YandexBot' => 'YandexBot',
        'Baiduspider' => 'Baiduspider',
        'DuckDuckBot' => 'DuckDuckBot',
        'PetalBot' => 'PetalBot',
        'AhrefsBot' => 'AhrefsBot',
        'SemrushBot' => 'SemrushBot',
        'MJ12bot' => 'MJ12bot',
        'DotBot' => 'DotBot',
        'Bytespider' => 'Bytespider',
        'GPTBot' => 'GPTBot',
        'ChatGPT-User' => 'ChatGPT-User',
        'OAI-SearchBot' => 'OAI-SearchBot',
        'ClaudeBot' => 'ClaudeBot',
        'PerplexityBot' => 'PerplexityBot',
        'CCBot' => 'CCBot',
        'Amazonbot' => 'Amazonbot',
        'meta-externalagent' => 'Meta crawler',
        'facebookexternalhit' => 'Meta crawler',
        'HeadlessChrome' => 'HeadlessChrome',
        'curl/' => 'curl',
        'Wget' => 'Wget',
        'python-requests' => 'python-requests',
        'python-urllib' => 'python-urllib',
        'aiohttp' => 'aiohttp',
        'Go-http-client' => 'Go-http-client',
        'GuzzleHttp' => 'GuzzleHttp',
        'okhttp' => 'okhttp',
        'Java/' => 'Java',
        'Scrapy' => 'Scrapy',
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'Firefox/' => 'Firefox',
        'Chrome/' => 'Chrome',
        'Safari/' => 'Safari',
    ];

    public function classify(string $userAgent): string
    {
        if ($userAgent === '' || $userAgent === '-') {
            return '';
        }
        foreach (self::LABELS as $needle => $label) {
            if (stripos($userAgent, $needle) !== false) {
                return $label;
            }
        }

        return preg_match('/bot|crawl|spider|scan/i', $userAgent) ? self::OTHER_BOT : self::OTHER;
    }
}
