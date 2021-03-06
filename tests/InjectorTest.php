<?php
namespace DI\Test;

use DI\Injector;

class InjectorTest extends \PHPUnit_Framework_TestCase {
	public function testDependencyResolutionSpeed() {
		$injector = new Injector();
		$simpleSpeed = 0;
		$complexSpeed = 0;
		$rounds = 1000;

		for($i = 0; $i < $rounds; $i++) {
			$start = microtime(true);
			$a = $injector->instantiate(A::class);
			$end = microtime(true);
			$simpleSpeed += ($end - $start) * 1000000;
		}

		for($i = 0; $i < $rounds; $i++) {
			$start = microtime(true);
			$d = $injector->instantiate(D::class);
			$end = microtime(true);
			$complexSpeed += ($end - $start) * 1000000;
		}

		$this->markTestSkipped('Simple injection: ' . ($simpleSpeed / $rounds) . 'ms, Complex injection: ' . ($complexSpeed / $rounds) . 'ms');
	}

	public function testThrowsOnUnknownDependency() {
		$this->setExpectedException(\DI\ReferenceNotFoundException::class);
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

	public function testRetrievesDependenciesFromCacheDuringResolution() {
		$injector = new Injector();
		$a = $injector->get(A::class);
		$c = $injector->get(C::class);
		$this->assertInstanceOf(A::class, $c->a);
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

	public function testReturnsTheResultOfAnInvocation() {
		$injector = new Injector();
		$fn = $injector->annotate(function () { return 42; });
		$this->assertSame(42, $fn());
	}

	public function testCreatesInstancesOfClasses() {
		$injector = new Injector();
		$d = $injector->instantiate(D::class);
		$this->assertInstanceOf(D::class, $d);
		$this->assertInstanceOf(C::class, $d->c);
		$this->assertInstanceOf(B::class, $d->c->b);
		$this->assertInstanceOf(A::class, $d->c->a);
	}

	public function testRetrievesDependenciesFromParents() {
		$parent = new Injector();
		$parent->bind(A::class, new B());
		$child = new Injector($parent);

		$a = $child->get(A::class);

		$this->assertInstanceOf(B::class, $a);
	}

	public function testOverridesDependenciesFromParents() {
		$parent = new Injector();
		$parent->bind(A::class, new A());

		$child = new Injector($parent);
		$child->bind(A::class, new B());

		$a = $child->get(A::class);
		$this->assertInstanceOf(B::class, $a);
	}

	public function testRetrievesInstancesFromCache() {
		$injector = new Injector();

		$a = $injector->get(A::class);
		$aprime = $injector->get(A::class);

		$this->assertSame($a, $aprime);
	}

	public function testRetrievesFunctionsFromCache() {
		$injector = new Injector();
		$phpunit = $this;
		$fn = function (A $a) use($phpunit) {};

		$a = $injector->annotate($fn);
		$aprime = $injector->annotate($fn);

		$this->assertSame($a, $aprime);
	}

	public function testHasChecksForInstances() {
		$injector = new Injector();
		$this->assertFalse($injector->has(A::class));
		$a = $injector->get(A::class);
		$this->assertTrue($injector->has(A::class));
	}

	public function testHasFollowsUpIntoParentInjectors() {
		$injector = new Injector();
		$child = new Injector($injector);
		$this->assertFalse($injector->has(A::class));
		$this->assertFalse($child->has(A::class));
		$a = $injector->get(A::class);
		$this->assertTrue($child->has(A::class));
	}

	public function testThrowsForNonInstantiableDependencies() {
		$injector = new Injector();
		$this->setExpectedException(\DI\DependencyException::class);
		$injector->instantiate(K::class);
	}

	public function testResolvesFromAnInterface() {
		$this->markTestIncomplete('Resolving an unknown subclass from an interface is impossible!');
		$injector = new Injector();
		$phpunit = $this;
		$fn = $injector->annotate(function (G $g) use($phpunit) { $phpunit->assertInstanceOf(I::class, $g); });
		$fn();
	}

	public function testResolvesFromABaseClass() {
		$this->markTestIncomplete('Resolving an unknown subclass from a base class is impossible!');
		$injector = new Injector();
		$phpunit = $this;
		$fn = $injector->annotate(function (H $h) use($phpunit) { $phpunit->assertInstanceOf(J::class, $h); });
		$fn();
	}
}

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

interface G {}

class H {}

class I implements G {}

class J extends H {}

abstract class K {}
