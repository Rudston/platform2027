<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The language switcher: /locale/{locale} stores a supported locale in the
 * session, which SetLocaleFromBrowser then applies on subsequent requests.
 */
class LocaleSwitchTest extends TestCase
{
    public function test_switching_to_a_supported_locale_stores_it_in_the_session(): void
    {
        $response = $this->get('/locale/pt_BR');

        $response->assertRedirect();
        $this->assertSame('pt_BR', session('locale'));
    }

    public function test_an_unsupported_locale_is_ignored(): void
    {
        $response = $this->get('/locale/zz');

        $response->assertRedirect();
        $this->assertNull(session('locale'));
    }

    public function test_the_session_locale_is_applied_to_the_app(): void
    {
        $this->withSession(['locale' => 'pt_BR'])->get('/');

        $this->assertSame('pt_BR', app()->getLocale());
    }

    public function test_a_stale_unsupported_session_locale_falls_back_to_default(): void
    {
        $this->withSession(['locale' => 'zz'])->get('/');

        $this->assertSame(config('app.locale'), app()->getLocale());
    }
}
