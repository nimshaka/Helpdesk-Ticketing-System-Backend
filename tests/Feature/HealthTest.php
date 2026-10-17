<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class HealthTest extends ApiTestCase
{
    public function test_health_endpoint_returns_the_standard_shape(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_tests_run_against_the_testing_database(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
    }
}
