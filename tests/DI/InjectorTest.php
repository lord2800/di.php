<?php

require_once('namespaces.php');

class Dependency {}
class Delegate extends Dependency { }
class Dependent { public $dep; public function __construct(Dependency $dep) { $this->dep = $dep; } }
class Callback { function called() {} }
class CallbackWithInvoke { public $called = false; function __invoke() { $this->called = true; } }
class CallbackWithDeps { public $inst; function __invoke(Dependency $dep) { $this->inst->assertInstanceOf('Dependency', $dep); } }
class CallbackWithDelegate { public $inst; function __invoke(Dependency $obj) { $this->inst->assertInstanceOf('Delegate', $obj); } }

class InjectorTest extends \PHPUnit_Framework_TestCase {
	private $injector, $dep, $dep2;

	public function setUp() {
		$this->injector = new \DI\Injector();
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

	public function testInjectShouldCallClosureDependencies() {
		$called = false;
		$p = function () use(&$called) { $called = true; return null; };
		$this->injector->provide('p', $p);
		$self = $this;
		$fn = function ($p) use($self) { $self->assertNull($p); };
		$cb = $this->injector->inject($fn);
		$this->assertTrue($called);
		$cb();
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
		$this->injector->provide('b', function () {
			return function () { return true; };
		});
		$cb = $this->injector->inject(function ($b) { return $b(); });
		$this->assertTrue($cb());
	}

	/**
	  * @expectedException RuntimeException
	  * @expectedExceptionMessage not found, tried namespaces
	  */
	public function testCreateShouldThrowForNonexistentClass() {
		$b = $this->injector->create('nonexistent');
		$this->assertNull($b);
	}

	/**
	  * @expectedException RuntimeException
	  * @expectedExceptionMessage not found, tried namespaces
	  */
	public function testCreateShouldThrowForNonexistentClassWithNamespaces() {
		$b = $this->injector->create('nonexistent', ['one', 'two']);
		$this->assertNull($b);
	}

	public function testClassWithNoCtorShouldStillInject() {
		$b = $this->injector->create('Dependency');
		$this->assertNotNull($b);
		$this->assertInstanceOf('Dependency', $b);
	}

	/**
	  * @expectedException RuntimeException
	  * @expectedExceptionMessage Duplicate dependency class Dependency
	  */
	public function testProvideShouldThrowForDuplicateClass() {
		$this->injector->provide('d1', new Dependency());
		$this->injector->provide('d2', new Dependency());
	}

	/**
	  * @expectedException RuntimeException
	  * @expectedExceptionMessage Duplicate dependency name d1
	  */
	public function testProvideShouldThrowForDuplicateName() {
		$this->injector->provide('d1', new Dependency());
		$this->injector->provide('d1', new Dependency());
	}

	public function testCreateShouldResolveNamespaces() {
		$this->injector->provide('b', new one\A());
		$b = $this->injector->create('A', ['two', 'one']);
		$this->assertNotNull($b);
		$this->assertInstanceOf('one\\A', $b);
	}

	public function testInvokableShouldBeInjected() {
		$this->injector->provide('a', new Dependency());
		$cb = new CallbackWithDeps();
		$cb->inst = $this;
		$fn = $this->injector->inject($cb);
		$fn();
	}

	public function testDelegateShouldReplaceInstance() {
		$injector = new \DI\Injector();
		$injector->provide('del', new Dependency());
		$injector->delegate('del', function () { return new Delegate(); });
		$cb = new CallbackWithDelegate();
		$cb->inst = $this;
		$fn = $injector->inject($cb);
		$fn();
	}
}
