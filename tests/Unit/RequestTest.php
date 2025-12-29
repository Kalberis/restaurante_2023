<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use Core\Request;
use Core\CsrfToken;

class RequestTest extends BaseTestCase
{
    public function test_get_method_sanitizes_input(): void
    {
        $_GET['test'] = '<script>alert("xss")</script>';
        
        $request = Request::getInstance();
        $value = $request->get('test');
        
        $this->assertStringNotContainsString('<script>', $value);
    }

    public function test_post_method_sanitizes_input(): void
    {
        $_POST['email'] = 'test@example.com';
        
        $request = Request::getInstance();
        $value = $request->post('email');
        
        $this->assertEquals('test@example.com', $value);
    }

    public function test_csrf_token_generation(): void
    {
        $token1 = CsrfToken::generate();
        $token2 = CsrfToken::generate();
        
        $this->assertEqual($token1, $token2); // Deve retornar o mesmo
        $this->assertNotEmpty($token1);
    }

    public function test_csrf_token_validation(): void
    {
        $token = CsrfToken::generate();
        
        $this->assertTrue(CsrfToken::validate($token));
        $this->assertFalse(CsrfToken::validate('invalid_token'));
    }

    public function test_get_method_returns_default(): void
    {
        $_GET = [];
        
        $request = Request::getInstance();
        $value = $request->get('nonexistent', 'default');
        
        $this->assertEquals('default', $value);
    }
}
