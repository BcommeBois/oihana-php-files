<?php

namespace oihana\files ;

/**
 * Indicates if the OS system is Mac.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function isMac():bool
{
    static $isMac = null ;
    if ( $isMac === null )
    {
        $isMac = strtoupper( substr(PHP_OS , 0 , 6 ) ) === 'DARWIN' ;
    }
    return $isMac ;
}