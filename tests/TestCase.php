<?php

namespace OscarWeijman\PhpElastic\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup code that should run before each test
    }
    
    protected function tearDown(): void
    {
        // Cleanup code that should run after each test
        
        parent::tearDown();
    }
}