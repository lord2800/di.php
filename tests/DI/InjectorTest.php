<?php

use \Mockery as m;

class InjectorTest extends PHPUnit_Framework_TestCase {
	private $injector, $dep, $dep2;

	public function setUp() {
		$this->injector = new DI\Injector();
		$this->dep = new Dependency;
	}

	public function testProvideAndRetrieve() {
		$this->injector->provide('dep', $this->dep);
		$this->assertEquals($this->dep, $this->injector->retrieve('dep'));
		$this->assertEquals($this->dep, $this->injector->retrieve('Dependency'));
	}

	public function testInjectShouldSucceedForClosures() {
		$this->injector->provide('n', $this->dep);
		$self = $this;
		$fn = function (Dependency $dep) use($self) {
			$self->assertEquals($self->dep, $dep);
		};
		$cb = $this->injector->inject($fn);
		$this->assertInstanceOf('Closure', $cb);
		$cb();
	}

	public function testInjectShouldSucceedForCallables() {
		$this->injector->provide('n', $this->dep);
		$self = $this;

		$m = new Callback();
		$cb = $this->injector->inject([$m, 'called']);
		$this->assertInstanceOf('Closure', $cb);
		$cb();
	}

	/**
	  * @expectedException RuntimeException
	  * @expectedExceptionMessage Could not satisfy dependency dep2
	  */
	public function testInjectShouldFailForUnknownDeps() {
		$fn = function ($dep2) {};
		$cb = $this->injector->inject($fn);
	}

	public function testCreateShouldInjectTheCtor() {
		$this->injector->provide('n', $this->dep);
		$dependent = $this->injector->create('Dependent');
		$this->assertEquals($this->dep, $dependent->dep);
	}

	public function testInstanceShouldBeASingleton() {
		$this->injector->provide('n', $this->dep);
		$dependent1 = $this->injector->instance('Dependent');
		$dependent2 = $this->injector->instance('Dependent');
		$this->assertSame($dependent1, $dependent2);
	}

	public function testDontCallObjectsWithMagicInvoke() {
		$cb = new CallbackWithInvoke();
		$this->injector->provide('dep2', $cb);
		$this->injector->inject(function ($dep2) {});
		$this->assertFalse($cb->called);
	}

	public function testInjectShouldReturnTheResultOfInvocation() {
		$this->injector->provide('b', function () { return true; });
		$cb = $this->injector->inject(function ($b) { return $b(); });
		$this->assertTrue($cb());
	}
}

class Dependency {}
class Dependent { public $dep; public function __construct(Dependency $dep) { $this->dep = $dep; } }
class Callback { function called() {} }
class CallbackWithInvoke { public $called = false; function __invoke() { $this->called = true; } }
