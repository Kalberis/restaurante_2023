<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use Core\FormValidator;

class FormValidatorTest extends BaseTestCase
{
    public function test_required_validation_fails_for_empty(): void
    {
        $validator = new FormValidator(['name' => '']);
        $validator->rules(['name' => 'required']);
        
        $this->assertFalse($validator->validate());
        $this->assertTrue($validator->hasError('name'));
    }

    public function test_required_validation_passes_for_filled(): void
    {
        $validator = new FormValidator(['name' => 'John']);
        $validator->rules(['name' => 'required']);
        
        $this->assertTrue($validator->validate());
        $this->assertFalse($validator->hasError('name'));
    }

    public function test_email_validation(): void
    {
        $valid = new FormValidator(['email' => 'test@example.com']);
        $valid->rules(['email' => 'email']);
        $this->assertTrue($valid->validate());

        $invalid = new FormValidator(['email' => 'invalid-email']);
        $invalid->rules(['email' => 'email']);
        $this->assertFalse($invalid->validate());
    }

    public function test_min_max_validation(): void
    {
        $validator = new FormValidator(['age' => 25]);
        $validator->rules(['age' => 'min:18|max:65']);
        
        $this->assertTrue($validator->validate());
    }

    public function test_minlength_maxlength_validation(): void
    {
        $validator = new FormValidator(['password' => 'securepass123']);
        $validator->rules(['password' => 'minlength:8|maxlength:50']);
        
        $this->assertTrue($validator->validate());
    }

    public function test_numeric_validation(): void
    {
        $valid = new FormValidator(['quantity' => '10']);
        $valid->rules(['quantity' => 'numeric']);
        $this->assertTrue($valid->validate());

        $invalid = new FormValidator(['quantity' => 'abc']);
        $invalid->rules(['quantity' => 'numeric']);
        $this->assertFalse($invalid->validate());
    }

    public function test_cpf_validation(): void
    {
        // CPF válido (exemplo)
        $valid = new FormValidator(['cpf' => '11144477735']);
        $valid->rules(['cpf' => 'cpf']);
        $this->assertTrue($valid->validate());

        $invalid = new FormValidator(['cpf' => '00000000000']);
        $invalid->rules(['cpf' => 'cpf']);
        $this->assertFalse($invalid->validate());
    }

    public function test_confirmed_validation(): void
    {
        $validator = new FormValidator([
            'password' => 'secret123',
            'password_confirmation' => 'secret123'
        ]);
        $validator->rules(['password' => 'confirmed']);
        
        $this->assertTrue($validator->validate());
    }

    public function test_in_validation(): void
    {
        $validator = new FormValidator(['status' => 'active']);
        $validator->rules(['status' => 'in:active,inactive,pending']);
        
        $this->assertTrue($validator->validate());
    }

    public function test_validated_returns_only_valid_fields(): void
    {
        $validator = new FormValidator([
            'name' => 'John',
            'email' => 'invalid',
            'age' => 25
        ]);
        $validator->rules([
            'name' => 'required',
            'email' => 'email',
            'age' => 'numeric'
        ]);
        
        $validator->validate();
        $validated = $validator->validated();
        
        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayNotHasKey('email', $validated); // Falhou
        $this->assertArrayHasKey('age', $validated);
    }
}
