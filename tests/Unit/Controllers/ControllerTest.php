<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Controller;
use Tests\TestCase;

class ControllerTest extends TestCase
{
    public function test_controller_is_abstract()
    {
        $reflection = new \ReflectionClass(Controller::class);
        $this->assertTrue($reflection->isAbstract());
    }

    public function test_controller_exists()
    {
        $this->assertTrue(class_exists(Controller::class));
    }

    public function test_controller_namespace()
    {
        $reflection = new \ReflectionClass(Controller::class);
        $this->assertEquals('App\Http\Controllers', $reflection->getNamespaceName());
    }
}