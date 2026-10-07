<?php

use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

define('LARAVEL_TESTING', true);

uses(TestCase::class, CreatesApplication::class)->in('tests/Feature', 'tests/Unit');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
