<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use App\Http\Middleware\VerifyCsrfToken;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // 419（CSRF mismatch）対策：テスト中は CSRF を無効化
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }
}
