<?php
/**
 * This file describes the Injector class
 * @package DI
 * @license MIT
 */
namespace DI;

use \ReflectionClass,
	\ReflectionFunction,
	\ReflectionFunctionAbstract,
	\Interop\Container\ContainerInterface as Container,
	\DI\ReferenceNotFoundException as RefNotFound,
	\DI\NotResolvableException as NotResolvable;

/**
 * The injector class deals with dependency management as well as object creation and function context invocation.
 * @api
 */
class Injector implements Container {
	private $instances = [], $parent = null, $fnCache = [];

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
			throw new RefNotFound($id);
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
			if(count($callable) !== 2) {
				throw new NotResolvable($callable);
			}
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
	 * Create an instance of a function with its' constructor dependency injected.
	 * @param string $id Class identifier. This must be the fully qualified class name.
	 * @return object The instance of your class, dependency injected via its' constructor.
	 */
	public function instantiate($id) {
		// if we already have one, return it
		if($this->has($id)) {
			return $this->get($id);
		}

		// make a new one
		$cls = new ReflectionClass($id);
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

	private function getArgs(ReflectionFunctionAbstract $fn) {
		$args = [];
		foreach($fn->getParameters() as $param) {
			$args[] = $this->instantiate($param->getClass()->getName());
		}
		return $args;
	}
}
