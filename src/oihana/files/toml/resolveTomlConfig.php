<?php

namespace oihana\files\toml ;

use Devium\Toml\TomlError;

use oihana\files\exceptions\FileException;
use oihana\files\enums\FileExtension;
use oihana\files\exceptions\DirectoryException;

use function oihana\core\arrays\deepMerge;
use function oihana\files\assertDirectory;
use function oihana\files\assertFile;
use function oihana\files\path\isAbsolutePath;
use function oihana\files\path\isBasePath;
use function oihana\files\path\joinPaths;
use function oihana\files\path\makeAbsolute;

/**
 * Resolves a TOML configuration file and merges it with a default configuration.
 *
 * This function attempts to load a TOML config file by:
 * - Ensuring the file path ends with the `.toml` extension.
 * - Resolving relative paths against the current working directory or a given base path.
 * - Validating the existence of the file and directories.
 * - Decoding the TOML content to an associative array.
 * - Deep merging the decoded config with the provided default configuration.
 * - Optionally applying an initialization callable to the merged configuration.
 *
 * If `$filePath` is null or empty, only the default configuration is returned (possibly processed by `$init`).
 *
 * @param string|null $filePath Path to the TOML file. If null or empty, only default config is used.
 * @param array|null $defaultConfig Default configuration array to merge with the TOML file contents.
 * @param string|null $defaultPath Base path used to resolve relative file paths when not absolute.
 * @param callable|null $init Optional callable that takes the merged config array as input
 * and returns a processed config array. Signature: `function(array): array`.
 *
 * @return array Merged (and optionally initialized) configuration array.
 *
 * @throws FileException           If the file path is invalid or the file does not exist.
 * @throws DirectoryException      If the provided default path is invalid or not a directory.
 * @throws TomlError               If the TOML content cannot be parsed.
 *
 * @author Marc Alcaraz (ekameleon)
 * @since 1.0.0
 * @package oihana\files\toml
 *
 * @example
 * ```php
 * use function oihana\files\toml\resolveTomlConfig;
 *
 * // Default config array
 * $defaultConfig =
 * [
 *     'database' =>
 *     [
 *         'host' => 'localhost',
 *         'port' => 3306,
 *     ],
 *     'debug' => false,
 * ];
 *
 * // Path to your TOML config file (can be relative or absolute)
 * $configFile = '/path/to/config'; // '.toml' appended automatically if missing
 *
 * // Optional base path to resolve relative paths
 * $basePath = '/var/www/project/configs';
 *
 * $init = function( array $config ): array
 * {
 *    // Custom post-processing of config, e.g. validation or transformation
 *    if (!isset($config['debug']))
 *    {
 *       $config['debug'] = true;
 *    }
 *    return $config;
 * };
 *
 * try
 * {
 *    $config = resolveTomlConfig( $configFile , $defaultConfig , $basePath , $init ) ;
 *    print_r( $config ) ;
 * }
 * catch ( FileException $e )
 * {
 *    echo "File error: " . $e->getMessage();
 * }
 * catch ( DirectoryException $e )
 * {
 *     echo "Directory error: " . $e->getMessage();
 * }
 * catch ( TomlError $e )
 * {
 *     echo "TOML parsing error: " . $e->getMessage();
 * }
 * ```
 */
function resolveTomlConfig
(
    ?string   $filePath ,
    ?array    $defaultConfig = []   ,
    ?string   $defaultPath   = null ,
    ?callable $init          = null ,
)
:array
{
    $defaultConfig = $defaultConfig ?? [] ;

    if( $filePath !== null && $filePath !== '' )
    {
        if( !str_ends_with( $filePath , FileExtension::TOML ) )
        {
            $filePath .= FileExtension::TOML ;
        }

        if( !isAbsolutePath( $filePath ) )
        {
            if( isBasePath( $filePath , getcwd() ) )
            {
                $filePath = makeAbsolute( $filePath  ,  getcwd() ) ;
            }
            else if( !empty( $defaultPath ) )
            {
                assertDirectory( $defaultPath ) ;
                $file = joinPaths( $defaultPath , $filePath ) ;
                if( is_file( $file ) )
                {
                    $filePath = $file ;
                }
            }
        }

        assertFile( $filePath ) ;

        $toml   = file_get_contents( $filePath ) ;
        $config = deepMerge( $defaultConfig , toml_decode( $toml , true ) ) ;
    }
    else
    {
        $config = deepMerge( $defaultConfig ) ;
    }

    return $init ? $init( $config ) : $config ;
}