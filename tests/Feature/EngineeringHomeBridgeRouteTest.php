<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EngineeringHomeBridgeRouteTest extends TestCase
{
    public function test_engineering_home_bridge_route_is_registered(): void
    {
        $this->assertTrue(Route::has('engineering.home.bridge'));

        $route = Route::getRoutes()->getByName('engineering.home.bridge');

        $this->assertNotNull($route);
        $this->assertSame(
            'engineering/{organization}/projects/{project}/home',
            $route->uri()
        );
    }
}
