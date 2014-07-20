<?php
/**
 * This file describes the Injector class
 * @license MIT
 */
namespace DI;

use \ReflectionClass,
	\ReflectionFunction,
	\ReflectionFunctionAbstract,
	\Interop\Container\ContainerInterface as Container,
	\DI\ReferenceNotFoundException as RefNotFound,
	\DI\DependencyException;

/**
 * The injector class deals with dependency management as well as object creation and function context invocation.
 * @api
 */
class Injector implements Container {
	/** @internal */
	private $instances = [], $parent = null, $fnCache = [];

	/**
	 * Create an instance of an injector with an optional parent.
	 * Provide a parent injector when you wish to optionally override some dependencies but not others
	 * @param \DI\Injector $parent The parent injector to optionally retrieve dependencies from
	 */
	public function __construct(Injector $parent = null) {
		$this->parent = $parent;
	}

	/**
	 * Retrieve an instance from the injector.
	 * @param string $id Class identifier. This must be the fully qualified class name.
	 * @return object The requested instance.
	 * @throws Interop\Container\Exception\NotFoundException if the class identifier was not found.
	 */
	public function get($id) {
		// if there's no parent and we don't have one, it's not found
		if($this->parent === null && !array_key_exists($id, $this->instances)) {
			// try to make it
			try {
				return $this->instantiate($id);
			} catch(\Exception $e) {
				// throw RefNotFound if we couldn't build one
				throw new RefNotFound($id);
			}
		}

		// return either ours or our parent's
		return array_key_exists($id, $this->instances) ? $this->instances[$id] : $this->parent->get($id);
	}

	/**
	 * Check for the existence of an instance from the injector.
	 * @param string $id Class identifier. This must be the fully qualified class name.
	 * @return bool True if the instance exists, otherwise false.
	 */
	public function has($id) {
		if(array_key_exists($id, $this->instances)) {
			return true;
		}

		return $this->parent !== null ? $this->parent->has($id) : false;
	}

	/**
	 * Bind a specified class identifier to a specified instance in the injector.
	 * @param string $id Class identifier. This must be the fully qualified class name.
	 * @param object $instance The instance to bind to the specified class identifier.
	 * @return object The requested instance.
	 */
	public function bind($id, $instance) {
		$this->instances[$id] = $instance;
	}

	/**
	 * Annotate a function so that it is dependency injected.
	 * @param callable $callable The function to annotate.
	 * @return A no-argument function that invokes the specified function with all of its' parameters dependency injected.
	 */
	public function annotate(callable $callable) {
		$fn = null;
		$invoke = function () {};

		// TODO can this be *any* cleaner?
		if(is_array($callable)) {
			// support shorthand array callable form
			// callable arrays will always have 2 elements--otherwise they don't pass the typehint
			$cls = new ReflectionClass($callable[0]);
			$fn = $cls->getMethod($callable[1]);
			$invoke = function ($args) use($fn, $callable) {
				return function () use($fn, $callable, $args) {
					$fn->invokeArgs($callable[0], $args);
				};
			};
		} else if(!($callable instanceof \Closure)) {
			// support magic __invoke method form
			$cls = new ReflectionClass($callable);
			$fn = $cls->getMethod('__invoke');
			$invoke = function ($args) use($fn, $callable) {
				return function () use($fn, $callable, $args) {
					$fn->invokeArgs($callable, $args);
				};
			};
		} else {
			// support all other callables
			$fn = new ReflectionFunction($callable);
			$invoke = function ($args) use($fn) {
				return function () use($fn, $args) {
					$fn->invokeArgs($args);
				};
			};
		}

		$cacheId = sha1($fn->__toString());
		if(array_key_exists($cacheId, $this->fnCache)) {
			return $this->fnCache[$cacheId];
		}

		$args = $this->getArgs($fn);
		$annotated = $invoke($args);
		$this->fnCache[$cacheId] = $annotated;
		return $annotated;
	}

	/**
	 * Create an instance of a class with its' constructor dependency injected. Always creates a new instance.
	 * Use {@link \DI\Injector::get() Injector->get()} if you want to retrieve the same instance each time.
	 * @param string $id Class identifier. This must be the fully qualified class name.
	 * @return object The instance of your class, dependency injected via its' constructor.
	 */
	public function instantiate($id) {
		$cls = new ReflectionClass($id);
		if(!$cls->isInstantiable()) {
			throw new DependencyException($id . ' is not instantiable!');
		}
		$ctor = $cls->getConstructor();
		// shortcut: if the class doesn't have a declared constructor, we can just create an instance of it with the no-arg ctor
		if($ctor === null) {
			$this->instances[$id] = $cls->newInstance();
			return $this->instances[$id];
		}

		$args = $this->getArgs($ctor);
		$annotated = function () use($cls, $args) { return $cls->newInstanceArgs($args); };
		$this->instances[$id] = $annotated();
		return $this->instances[$id];
	}

	/** @internal */
	private function getArgs(ReflectionFunctionAbstract $fn) {
		$args = [];
		foreach($fn->getParameters() as $param) {
			$name = $param->getClass()->getName();
			if(!$this->has($name)) {
				$args[] = $this->instantiate($param->getClass()->getName());
			} else {
				$args[] = $this->get($name);
			}
		}
		return $args;
	}
}
