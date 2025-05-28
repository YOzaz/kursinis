<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\WebController;
use Tests\TestCase;

class WebControllerTest extends TestCase
{
    private WebController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new WebController();
    }

    public function test_controller_can_be_instantiated()
    {
        $this->assertInstanceOf(WebController::class, $this->controller);
    }

    public function test_controller_extends_base_controller()
    {
        $this->assertInstanceOf(\App\Http\Controllers\Controller::class, $this->controller);
    }

    public function test_index_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'index'));
    }

    public function test_upload_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'upload'));
    }

    public function test_progress_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'progress'));
    }

    public function test_validate_json_structure_method_exists()
    {
        $this->assertTrue(method_exists($this->controller, 'validateJsonStructure'));
    }

    public function test_index_method_has_no_parameters()
    {
        $reflection = new \ReflectionMethod($this->controller, 'index');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(0, $parameters);
    }

    public function test_upload_method_has_request_parameter()
    {
        $reflection = new \ReflectionMethod($this->controller, 'upload');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertEquals('request', $parameters[0]->getName());
    }

    public function test_progress_method_has_job_id_parameter()
    {
        $reflection = new \ReflectionMethod($this->controller, 'progress');
        $parameters = $reflection->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertEquals('jobId', $parameters[0]->getName());
    }

    public function test_controller_uses_correct_namespace()
    {
        $reflection = new \ReflectionClass(WebController::class);
        $this->assertEquals('App\Http\Controllers', $reflection->getNamespaceName());
    }

    public function test_controller_has_doc_comment()
    {
        $reflection = new \ReflectionClass(WebController::class);
        $docComment = $reflection->getDocComment();
        
        $this->assertIsString($docComment);
        $this->assertStringContainsString('Web sąsajos kontroleris', $docComment);
    }
}