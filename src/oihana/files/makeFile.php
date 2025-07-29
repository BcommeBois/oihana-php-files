<?php

namespace oihana\files ;

use oihana\files\enums\MakeFileOption;
use oihana\files\exceptions\DirectoryException;
use oihana\files\exceptions\FileException;

/**
 * Creates or updates a file with the given content and options.
 *
 * This function writes content to the specified file path. It supports
 * appending to existing files, overwriting, setting file permissions,
 * changing ownership, and group. It can also create the parent directories
 * if needed.
 *
 * Usage:
 * - Classic signature:
 * ```php
 * makeFile(string $filePath, string $content = '', array $options = []);
 * ```
 *
 * - Signature with options array as first parameter:
 * ```php
 * makeFile(array $options);
 * ```
 *
 * Required keys in $options:
 * - 'filePath' (string): The path of the file to create or modify (mandatory).
 * - 'content' (string): The content to write into the file (optional, default '').
 * Other keys correspond to options (see below).
 *
 * @param array{
 *     append:bool ,
 *     content:string|null ,
 *     file:string|null ,
 *     force:bool ,
 *     group:null|string ,
 *     lock:bool ,
 *     overwrite:bool ,
 *     permissions:int ,
 *     owner:string|null
 * }|string|null $fileOrOptions Either the file path as a string (classic usage),
 * or an associative array containing at least 'filePath' and optionally 'content' and other options.
 * @param string|null $content The content to write into the file. Defaults to empty string. Ignored if $filePathOrOptions is array.
 * @param array{ append:bool , force:bool , group:null|string , lock:bool , overwrite:bool , permissions:int , owner:string|null } $options An associative array of options:
 * - 'append' (bool): If true, appends content instead of overwriting. Default: false.
 * - 'force' (bool): If true, creates parent directories if they do not exist. Default: true.
 * - 'group' (string|null): Group name or ID to set as file group owner. Default: null.
 * - 'lock' (bool): If true, uses an exclusive lock while writing. Default: true.
 * - 'overwrite' (bool): If true, overwrites existing files. Default: false.
 * - 'permissions' (int): File permissions to set (octal). Default: 0644.
 * - 'owner' (string|null): User name or ID to set as file owner. Default: null.
 *
 * @return string The path of the created or updated file.
 *
 * @throws FileException If the file path is invalid, writing fails, or permission/ownership changes fail.
 * @throws DirectoryException If directory creation fails.
 *
 * @package oihana\files
 * @author Marc Alcaraz (ekameleon)
 * @since 1.0.0
 *
 * @example
 * Create a new file with content, creating directories if needed
 * ```php
 * makeFile('/path/to/file.txt', "Hello World");
 * ```
 * Append content to an existing file, creating directories if needed
 * ```php
 * makeFile('/path/to/file.txt', "\nAppended line", ['append' => true]);
 * ```
 *
 * Overwrite existing file with new content
 * ```php
 * makeFile('/path/to/file.txt', "Overwrite content", ['overwrite' => true]);
 * ```
 *
 * Create a file with custom permissions and without locking
 * ```php
 * makeFile('/path/to/file.txt', "Content", ['permissions' => 0600, 'lock' => false]);
 * ```
 *
 * Create a file and set ownership and group (requires appropriate permissions)
 * ```php
 * makeFile('/path/to/file.txt', "Content", ['owner' => 'username', 'group' => 'groupname']);
 * ```
 *
 * Create a file without forcing directory creation (will fail if directory missing)
 * ```php
 * makeFile('/path/to/file.txt', "Content", ['force' => false]);
 * ```
 *
 * With a unique array definition :
 * ```php
 *  makeFile([
 *      'file'        => '/path/to/file.txt',
 *      'content'     => "Hello World",
 *      'append'      => true,
 *      'permissions' => 0600,
 *  ]);
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function makeFile( array|string|null $fileOrOptions , ?string $content = null , array $options = [] ): string
{
    if ( is_array( $fileOrOptions ) )
    {
        $opts = $fileOrOptions ;

        $filePath = $opts[ MakeFileOption::FILE ] ?? null;
        if ( empty( $filePath ) )
        {
            throw new FileException('File path cannot be null or empty.');
        }

        $content = $opts[ MakeFileOption::CONTENT ] ?? '' ;

        unset( $opts[ MakeFileOption::FILE ] , $opts[ MakeFileOption::CONTENT ] );

        $options = $opts ;
    }
    else
    {
        $filePath = $fileOrOptions;
        if ($content === null)
        {
            $content = '' ;
        }
    }

    if ( empty( $filePath ) )
    {
        throw new FileException('File path cannot be null or empty.' ) ;
    }

    $append      = $options[ MakeFileOption::APPEND      ] ?? false ;
    $force       = $options[ MakeFileOption::FORCE       ] ?? true ;
    $group       = $options[ MakeFileOption::GROUP       ] ?? null  ;
    $lock        = $options[ MakeFileOption::LOCK        ] ?? true  ;
    $overwrite   = $options[ MakeFileOption::OVERWRITE   ] ?? false ;
    $permissions = $options[ MakeFileOption::PERMISSIONS ] ?? 0644  ;
    $owner       = $options[ MakeFileOption::OWNER       ] ?? null  ;

    if ( file_exists( $filePath ) && !$overwrite && !$append )
    {
        if ( !is_writable( $filePath ) )
        {
            throw new FileException( sprintf('File "%s" exists and is not writable.', $filePath ) ) ;
        }
        return $filePath;
    }

    if ( $force )
    {
        makeDirectory(  dirname( $filePath ) ) ;
    }

    $writeMode = $append ? FILE_APPEND : 0 ;
    if ( $lock )
    {
        $writeMode |= LOCK_EX ;
    }

    if ( @file_put_contents( $filePath , $content , $writeMode ) === false )
    {
        throw new FileException( sprintf('Failed to write to file "%s".', $filePath ) );
    }

    if ( !chmod( $filePath , $permissions ) )
    {
        throw new FileException(sprintf('Failed to set permissions %o on file "%s".', $permissions , $filePath ) ) ;
    }

    if ( $owner !== null && !chown( $filePath , $owner ) )
    {
        throw new FileException( sprintf( 'Failed to change owner to "%s" for file "%s".' , $owner , $filePath ) ) ;
    }

    if ( $group !== null && !chgrp( $filePath , $group ) )
    {
        throw new FileException(sprintf('Failed to change group to "%s" for file "%s".', $group , $filePath ) );
    }

    return $filePath ;
}