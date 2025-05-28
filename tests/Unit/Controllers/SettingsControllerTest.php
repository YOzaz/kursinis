<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\SettingsController;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    private SettingsController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SettingsController();
    }

    public function test_controller_can_be_instantiated()
    {
        $this->assertInstanceOf(SettingsController::class, $this->controller);
    }

    public function test_controller_extends_base_controller()
    {
        $this->assertInstanceOf(\App\Http\Controllers\Controller::class, $this->controller);
    }

    public function test_index_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'index'));
    }

    public function test_update_defaults_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'updateDefaults'));
    }

    public function test_index_method_has_no_parameters()
    {
        $reflection = new \ReflectionMethod($this->controller, 'index');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(0, $parameters);
    }

    public function test_update_defaults_method_has_request_parameter()
    {
        $reflection = new \ReflectionMethod($this->controller, 'updateDefaults');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertEquals('request', $parameters[0]->getName());
    }

    public function test_controller_uses_correct_namespace()
    {
        $reflection = new \ReflectionClass(SettingsController::class);
        $this->assertEquals('App\Http\Controllers', $reflection->getNamespaceName());
    }
}