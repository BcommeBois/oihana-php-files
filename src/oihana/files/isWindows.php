<?php

namespace oihana\files ;

/**
 * Indicates if the OS system is Windows.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function isWindows():bool
{
    static $isWindows = null ;
    if ( $isWindows === null )
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
    return $isWindows ;
}