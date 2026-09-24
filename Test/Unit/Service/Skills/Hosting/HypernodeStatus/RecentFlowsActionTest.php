<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Hypernode\Test\Unit\Service\Skills\Hosting\HypernodeStatus;

use MagoAssistant\Hypernode\Service\Skills\Hosting\HypernodeStatus\RecentFlowsAction;
use MagoAssistant\Hypernode\Test\Unit\Fakes\FakeApiClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RecentFlowsActionTest extends TestCase
{
    #[Test]
    public function itFlattensTheFlowsAndCountsTheRunningOnes(): void
    {
        $client = (new FakeApiClient())->withFlows([
            'count' => 3,
            'results' => [
                ['name' => 'update_node', 'state' => null, 'created_at' => '2026-09-24T10:00:00Z', 'progress' => ['total' => 8, 'completed' => 2]],
                ['name' => 'create_backup', 'state' => 'success', 'created_at' => '2026-09-24T09:00:00Z', 'updated_at' => '2026-09-24T09:01:00Z', 'progress' => ['total' => 2, 'completed' => 2], 'tracker' => ['description' => 'nightly']],
                ['name' => 'update_node', 'state' => 'success'],
            ],
        ]);

        $result = (new RecentFlowsAction($client))->execute(['limit' => 2], 1);

        self::assertSame(3, $result['total']);
        self::assertSame(1, $result['running']);
        self::assertCount(2, $result['flows']);
        self::assertSame('running', $result['flows'][0]['state']);
        self::assertSame(2, $result['flows'][0]['completed_steps']);
        self::assertSame('nightly', $result['flows'][1]['description']);
    }
}
