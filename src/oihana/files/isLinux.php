<?php

namespace oihana\files ;

/**
 * Indicates if the OS system is Linux.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function isLinux(): bool
{
    static $isLinux = null ;
    if ( $isLinux === null )
    {
        $isLinux = strtoupper( substr(PHP_OS , 0 , 5 ) ) === 'LINUX' ;
    }
    return $isLinux ;
}