<?php
namespace DI\Test;

use DI\Injector;

class A {}
class B {public function __construct() {}}

class C {
	public $a, $b;
	public function __construct(A $a, B $b) {
		$this->a = $a;
		$this->b = $b;
	}
}
class D {
	public $c;
	public function __construct(C $c) { $this->c = $c; }
}

class E {
	public $a;
	public function e(A $a) { $this->a = $a; }
}

class F {
	public $a;
	public function __invoke(A $a) { $this->a = $a; }
}

class InjectorTest extends \PHPUnit_Framework_TestCase {
	public function testThrowsOnUnknownDependency() {
		$this->setExpectedException('\Interop\Container\Exception\NotFoundException');
		$injector = new Injector();
		$injector->get('\\UnknownClass');
	}

	public function testResolvesSimpleDependencies() {
		$injector = new Injector();
		$phpunit = $this;
		$fn = $injector->annotate(function (A $a) use($phpunit) { $phpunit->assertInstanceOf(A::class, $a); });
		$fn();
		$fn = $injector->annotate(function (B $b) use($phpunit) { $phpunit->assertInstanceOf(B::class, $b); });
		$fn();
	}

	public function testResolvesComplexDependencies() {
		$injector = new Injector();
		$phpunit = $this;
		$fn = $injector->annotate(function (D $d) use($phpunit) {
			$phpunit->assertInstanceOf(D::class, $d);
			$phpunit->assertInstanceOf(C::class, $d->c);
			$phpunit->assertInstanceOf(B::class, $d->c->b);
			$phpunit->assertInstanceOf(A::class, $d->c->a);
		});
		$fn();
	}

	public function testSupportsArrayCallableNotation() {
		$e = new E();
		$injector = new Injector();
		$fn = $injector->annotate([$e, 'e']);
		$fn();
		$this->assertInstanceOf(A::class, $e->a);
	}

	public function testSupportsMagicCallableNotation() {
		$f = new F();
		$injector = new Injector();
		$fn = $injector->annotate($f);
		$fn();
		$this->assertInstanceOf(A::class, $f->a);
	}

	public function testCreatesInstancesOfClasses() {
		$injector = new Injector();
		$d = $injector->instantiate(D::class);
		$this->assertInstanceOf(D::class, $d);
		$this->assertInstanceOf(C::class, $d->c);
		$this->assertInstanceOf(B::class, $d->c->b);
		$this->assertInstanceOf(A::class, $d->c->a);
	}

	// would be nice to assert this fact, but it's flaky
	protected function ResolvesSimpleDependenciesInUnder50Microseconds() {
		$injector = new Injector();
		$start = microtime(true);
		$a = $injector->instantiate(A::class);
		$end = microtime(true);
		$this->assertInstanceOf(A::class, $a);
		$elapsedMicroseconds = ($end - $start) * 1000000;
		$this->assertLessThan(50, $elapsedMicroseconds);
	}

	// would be nice to assert this fact, but it's flaky
	protected function ResolvesComplexDependenciesInUnder500Microseconds() {
		$injector = new Injector();
		$start = microtime(true);
		$d = $injector->instantiate(D::class);
		$end = microtime(true);
		$this->assertInstanceOf(D::class, $d);
		$this->assertInstanceOf(C::class, $d->c);
		$this->assertInstanceOf(B::class, $d->c->b);
		$this->assertInstanceOf(A::class, $d->c->a);
		$elapsedMicroseconds = ($end - $start) * 1000000;
		$this->assertLessThan(500, $elapsedMicroseconds);
	}

	public function testRetrievesDependenciesFromParents() {
		$parent = new Injector();
		$parent->bind(A::class, new B());
		$child = new Injector($parent);
		$a = $child->instantiate(A::class);
		$this->assertInstanceOf(B::class, $a);
	}

	public function testOverridesDependenciesFromParents() {
		$parent = new Injector();
		$parent->bind(A::class, new A());
		$child = new Injector($parent);
		$child->bind(A::class, new B());
		$a = $child->instantiate(A::class);
		$this->assertInstanceOf(B::class, $a);
	}

	public function testRetrievesInstancesFromCache() {
		$injector = new Injector();
		$a = $injector->instantiate(A::class);
		$aprime = $injector->instantiate(A::class);
		$this->assertEquals($a, $aprime);
	}

	public function testRetrievesFunctionsFromCache() {
		$injector = new Injector();
		$phpunit = $this;
		$fn = function (A $a) use($phpunit) { $phpunit->assertInstanceOf(A::class, $a); };
		$a = $injector->annotate($fn);
		$aprime = $injector->annotate($fn);
		$this->assertEquals($a, $aprime);
		$a();
		$aprime();
	}
}
