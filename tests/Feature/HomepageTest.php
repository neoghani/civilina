<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomepageTest extends TestCase
{
    public function test_homepage_uses_the_civilina_blade_experience(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Digitalisez.')
            ->assertSee('Civilina AI')
            ->assertSee('data-reasoning-runtime="client"', false);
    }
}
