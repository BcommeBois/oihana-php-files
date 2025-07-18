<?php

namespace oihana\files\exceptions;

use Exception;

/**
 * Exception thrown when an unsupported compression type is encountered.
 *
 * This exception is used to indicate that a provided compression type or
 * Phar compression constant is not recognized or not supported by the system.
 *
 * It is typically thrown by utility functions dealing with archive formats,
 * such as {@see \oihana\files\phar\getPharCompressionType()} or
 * {@see \oihana\files\phar\getPharCompressionName()}.
 *
 * @package oihana\files\exceptions
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @example
 * ```php
 * use oihana\files\phar\getPharCompressionType;
 * use oihana\files\exceptions\UnsupportedCompressionException;
 *
 * try {
 *     $type = getPharCompressionType('xz'); // unsupported
 * } catch (UnsupportedCompressionException $e) {
 *     echo "Error: " . $e->getMessage();
 * }
 * ```
 */
class UnsupportedCompressionException extends Exception
{

}