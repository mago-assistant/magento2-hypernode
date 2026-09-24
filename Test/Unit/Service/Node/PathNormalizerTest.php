<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Service\Node;

use MagoAssistant\Hypernode\Service\Node\PathNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PathNormalizerTest extends TestCase
{
    public static function uris(): array
    {
        return [
            'query string' => ['/search?q=john@example.com', '/search'],
            'admin secret key' => ['/admin/sales/order/view/order_id/5/key/8f2a9c1d3e4b5a6f7c8d9e0f1a2b3c4d/', '/admin/sales/order/view/order_id/5/key/*/'],
            'password reset token' => ['/customer/account/createPassword/token/0aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456/', '/customer/account/createPassword/token/*/'],
            'ordinary path' => ['/women/tops-women/jackets-women.html', '/women/tops-women/jackets-women.html'],
            'short ids stay' => ['/rest/V1/products/SKU-1234', '/rest/V1/products/SKU-1234'],
        ];
    }

    #[Test]
    #[DataProvider('uris')]
    public function itStripsSecretsFromPaths(string $uri, string $expected): void
    {
        self::assertSame($expected, (new PathNormalizer())->normalize($uri));
    }
}
