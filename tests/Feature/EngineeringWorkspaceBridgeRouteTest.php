<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EngineeringWorkspaceBridgeRouteTest extends TestCase
{
    public function test_workspace_bridge_route_is_registered(): void
    {
        $this->assertTrue(Route::has('engineering.workspace.bridge'));

        $route = Route::getRoutes()->getByName('engineering.workspace.bridge');

        $this->assertNotNull($route);
        $this->assertSame(
            'engineering/{organization}/projects/{project}/workspace-safe',
            $route->uri()
        );
    }
}
