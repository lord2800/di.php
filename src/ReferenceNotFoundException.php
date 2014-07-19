<?php
namespace DI;

class ReferenceNotFoundException extends \RuntimeException implements \Interop\Container\Exception\NotFoundException {
	public function __construct($id) { parent::__construct(sprintf("Couldn't find %s", $id)); }
}
