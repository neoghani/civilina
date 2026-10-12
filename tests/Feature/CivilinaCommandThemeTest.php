<?php

namespace Tests\Feature;

use Tests\TestCase;

class CivilinaCommandThemeTest extends TestCase
{
    public function test_command_theme_assets_exist(): void
    {
        $this->assertFileExists(public_path('css/civilina-command-theme.css'));
        $this->assertFileExists(public_path('js/civilina-command-theme.js'));
        $this->assertFileExists(resource_path('views/components/civilina-command-theme.blade.php'));
    }
}
