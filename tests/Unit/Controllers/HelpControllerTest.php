<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\HelpController;
use Tests\TestCase;

class HelpControllerTest extends TestCase
{
    private HelpController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new HelpController();
    }

    public function test_controller_can_be_instantiated()
    {
        $this->assertInstanceOf(HelpController::class, $this->controller);
    }

    public function test_controller_extends_base_controller()
    {
        $this->assertInstanceOf(\App\Http\Controllers\Controller::class, $this->controller);
    }

    public function test_index_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'index'));
    }

    public function test_faq_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'faq'));
    }

    public function test_index_method_has_no_parameters()
    {
        $reflection = new \ReflectionMethod($this->controller, 'index');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(0, $parameters);
    }

    public function test_faq_method_has_no_parameters()
    {
        $reflection = new \ReflectionMethod($this->controller, 'faq');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(0, $parameters);
    }

    public function test_controller_uses_correct_namespace()
    {
        $reflection = new \ReflectionClass(HelpController::class);
        $this->assertEquals('App\Http\Controllers', $reflection->getNamespaceName());
    }

    public function test_both_methods_are_public()
    {
        $indexReflection = new \ReflectionMethod($this->controller, 'index');
        $faqReflection = new \ReflectionMethod($this->controller, 'faq');
        
        $this->assertTrue($indexReflection->isPublic());
        $this->assertTrue($faqReflection->isPublic());
    }
}