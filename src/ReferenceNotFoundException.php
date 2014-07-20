<?php
/**
 * This file describes the ReferenceNotFoundException class
 * @license MIT
 */
namespace DI;

/**
 * The ReferenceNotFoundException class represents when the {@link \DI\Injector Injector} can't find your specified reference.
 * @api
 */
class ReferenceNotFoundException extends \RuntimeException implements \Interop\Container\Exception\NotFoundException {
	/**
	 * Create an instance of the ReferenceNotFoundException class.
	 * @param string $id The fully qualified class name that couldn't be found
	 */
	public function __construct($id) { parent::__construct(sprintf("Couldn't find %s", $id)); }
}
