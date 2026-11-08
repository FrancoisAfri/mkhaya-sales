<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

/** After signing in or unlocking, nobody is sent "back" to a file such as /sw.js. */
class IntendedUrlIsAPageTest extends TestCase
{
    public function test_a_remembered_file_address_is_dropped(): void
    {
        foreach (['/sw.js', '/build/assets/app.js', '/favicon.ico', '/manifest.webmanifest', '/api/user'] as $file) {
            $this->withSession(['url.intended' => url($file)])->get('/login')->assertSessionMissing('url.intended');
        }
    }

    public function test_a_remembered_page_is_kept(): void
    {
        $this->withSession(['url.intended' => url('/manufacturing/intelligence/feed')])->get('/login')
            ->assertSessionHas('url.intended', url('/manufacturing/intelligence/feed'));
    }
}
