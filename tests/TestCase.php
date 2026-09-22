<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Inertia feature tests render full pages via the `page` view data
        // without needing real compiled assets; the built Vite manifest
        // only covers pages that existed at the last `npm run build` and
        // would otherwise throw for admin pages added in later tasks.
        $this->withoutVite();

        // Backend tasks add routes/controllers that render Inertia pages
        // before the corresponding Vue components exist (those land in a
        // later, frontend-focused task). Skip the on-disk existence check
        // so `assertInertia()` can still verify component name and props.
        config(['inertia.testing.ensure_pages_exist' => false]);
    }
}
