<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Caso de teste base com setup/teardown padrão
 */
abstract class BaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup comum para todos os testes
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Cleanup comum para todos os testes
    }
}
