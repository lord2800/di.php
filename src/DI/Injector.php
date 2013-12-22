<?php
namespace DI;

use \RuntimeException,
	\ReflectionClass,
	\ReflectionFunction,
	\ReflectionFunctionAbstract;

/**
 * The injector class deals with dependency management as well as object creation and function context invocation.
 */
class Injector {
	private $instances = [], $classcache = [], $namecache = [];

	private function getFullyQualifiedClassName($class, $namespaces) {
		$fqn = $class;
		$exists = class_exists($fqn);
		if(!$exists) {
			foreach($namespaces as $namespace) {
				$fqn = $namespace . '\\' . $class;
				if(class_exists($fqn)) {
					$exists = true;
					break;
				}
			}
		}

		if(!$exists) {
			array_unshift($namespaces, $class);
			$nslist = array_reduce($namespaces, function (&$r, $ns) { return $r . ', ' . $ns; }, '');
			throw new RuntimeException(sprintf('Class %1$s not found, tried namespaces: %s', $nslist));
		}
		return $fqn;
	}

	private function internalInject(ReflectionFunctionAbstract $ref) {
		$args = [];
		foreach($ref->getParameters() as $parameter) {
			$dependency = null;

			// try by typehint first, if that fails try by name
			$cls = $parameter->getClass();
			if($cls !== null) {
				$name = $cls->getName();
				if($name !== null) {
					$dependency = $this->retrieve($name);
				}
			}

			if($dependency === null) {
				$dependency = $this->retrieve($parameter->getName());
			}

			if($dependency !== null) {
				// TODO better check here--don't call objects with __invoke
				if(is_callable($dependency) && !is_object($dependency)) {
					$dependency = $dependency();
				}
				$args[$parameter->getPosition()] = $dependency;
			} else {
				throw new RuntimeException(sprintf('Could not satisfy dependency %s', $parameter->getName()));
			}
		}
		return $args;
	}

	/**
	 * Provide a dependency to the injector. You may make use of the dependency either by classname (of the object) or
	 * by the provided name (in the case of a closure)
	 * @param string [$name] The name of the dependency being provided
	 * @param mixed [$obj] The actual object backing the dependency (which may be a closure)
	 * @throws RuntimeException If the provided dependency name or class already exists
	 */
	public function provide($name, $obj) {
		// store dependencies by class and by name, so you can match by either the typehint or the parameter name
		$class = get_class($obj);
		if(isset($this->namecache[$name])) {
			throw new RuntimeException(sprintf('Duplicate dependency name %s', $name));
		}
		if(isset($this->classcache[$class])) {
			throw new RuntimeException(sprintf('Duplicate dependency class %s', $class));
		}
		$this->namecache[$name] = $obj;
		// don't add closures to the class cache--it's only for concrete instances (which means this won't trip up the isset above, either)
		if($class !== 'Closure') {
			$this->classcache[$class] = $obj;
		}
	}

	/**
	  * Fetch a dependency provided to the injector.
	  * @param string [$name] The name of the dependency to retrieve
	  * @return mixed The dependency, or null if not found
	  */
	public function retrieve($name) {
		// the class cache is more robust, so check it first
		if(isset($this->classcache[$name])) {
			return $this->classcache[$name];
		}
		if(isset($this->namecache[$name])) {
			return $this->namecache[$name];
		}
		return null;
	}

	/**
	  * Inject a function and return a closure that will invoke the injected function
	  * @param callable [$closure] The closure to inject dependencies into
	  * @return Closure A no-argument function that will invoke the closure with its' dependencies
	  */
	public function inject(callable $closure) {
		$ref = null;
		if(is_array($closure)) {
			$inst = $closure[0];
			$ref = (new ReflectionClass($inst))->getMethod($closure[1]);
			$args = $this->internalInject($ref);
			return function () use($ref, $args, $inst) { $ref->invokeArgs($inst, $args); };
		} else {
			$ref = new ReflectionFunction($closure);
			$args = $this->internalInject($ref);
			return function () use($ref, $args) { $ref->invokeArgs($args); };
		}
		throw new RuntimeException('Unable to determine how to invoke callable');
	}

	/**
	 * Get an instance of the specified class
	 * @param string [$class] The name of the class (either full or partial) to look up
	 * @param array [$namespaces] The list of namespaces to try and retrieve the class from
	 * @return mixed The singleton instance of the specified class
	 */
	public function instance($class, array $namespaces = []) {
		$class = $this->getFullyQualifiedClassName($class, $namespaces);
		if(!isset($this->instances[$class])) {
			$instance = $this->create($class, $namespaces);
			$this->instances[$class] = $instance;
		} else {
			$instance = $this->instances[$class];
		}
		return $instance;
	}

	/**
	 * Create a new instance of the specified class
	 * @see get
	 * @param string [$class] The name of the class (either full or partial) to look up
	 * @param array [$namespaces] The list of namespaces to try and retrieve the class from
	 * @return mixed The instance of the specified class
	 */
	public function create($class, array $namespaces = []) {
		$instance = null;
		$fqn = $this->getFullyQualifiedClassName($class, $namespaces);

		$ref = new ReflectionClass($fqn);
		$ctor = $ref->getConstructor();

		if($ctor !== null) {
			// if the class has a ctor, inject the dependencies it wants
			$args = $this->internalInject($ctor);
			return $ref->newInstanceArgs($args);
		}

	}
}
