<?php
namespace DI;

class NotResolvableException extends \RuntimeException implements \Interop\Container\Exception\ContainerException {
	public function __construct($ref) { parent:__construct(sprintf("Unable to resolve reference %s", var_export($ref))); }
}
