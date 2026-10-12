<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CivilinaHubBridgeRouteTest extends TestCase
{
    public function test_civilina_hub_bridge_route_is_registered(): void
    {
        $this->assertTrue(Route::has('civilina.home.bridge'));

        $route = Route::getRoutes()->getByName('civilina.home.bridge');

        $this->assertNotNull($route);
        $this->assertSame(
            'civilina/{organization}/projects/{project}/home',
            $route->uri()
        );
    }
}
