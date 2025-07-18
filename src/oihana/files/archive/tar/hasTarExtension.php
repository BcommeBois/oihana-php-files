<?php

namespace oihana\files\archive\tar;

use oihana\enums\Char;
use oihana\files\enums\FileExtension;

/**
 * Checks if a file has a tar-related extension.
 *
 * @param string $filePath Path to the file.
 * @param string[] $tarExtensions Optional list of valid tar-related extensions.
 * Defaults to common tar and compressed tar extensions:
 * - `.tar`
 * - `.tgz`
 * - `.gz`
 * - `.tar.gz`
 * - `.tar.bz2`
 * - `.bz2`
 *
 * @return bool True if the file has a recognized tar extension.
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 *
 * @example
 * Check a simple tar file:
 * ```php
 * var_dump(hasTarExtension('/path/to/archive.tar')); // bool(true)
 * ```
 *
 * Check a gzipped tar file:
 * ```php
 * var_dump(hasTarExtension('/path/to/archive.tar.gz')); // bool(true)
 * ```
 *
 * Check a file with .tgz extension:
 * ```php
 * var_dump(hasTarExtension('/path/to/archive.tgz')); // bool(true)
 * ```
 *
 * Check a file with unsupported extension:
 * ```php
 * var_dump(hasTarExtension('/path/to/archive.zip')); // bool(false)
 * ```
 * Check a file with double extension .tar.bz2:
 * ```php
 * var_dump(hasTarExtension('/path/to/archive.tar.bz2')); // bool(true)
 * ```
 */
function hasTarExtension( string $filePath , array $tarExtensions  =
[
    FileExtension::TAR ,
    FileExtension::TGZ ,
    FileExtension::GZ ,
    FileExtension::TAR_GZ ,
    FileExtension::TAR_BZ2 ,
    FileExtension::BZ2
]): bool
{
    $extension = Char::DOT . strtolower( pathinfo( $filePath , PATHINFO_EXTENSION ) );

    $filename        = pathinfo( $filePath , PATHINFO_FILENAME );
    $secondExtension = Char::DOT . strtolower( pathinfo( $filename , PATHINFO_EXTENSION ) );
    $fullExtension   = $secondExtension . $extension;

    return in_array( $extension , $tarExtensions ) || in_array( $fullExtension , $tarExtensions );
}

