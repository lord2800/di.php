<?php
namespace DI;

use \Interop\Container\Exception\ContainerException;

/**
 * An error representing a problem with a dependency state.
 */
class DependencyException extends \RuntimeException implements ContainerException {
}
