<?php

namespace oihana\files ;

/**
 * Indicates if the OS system is not Windows, Mac or Linux.
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function isOtherOS():bool
{
    return !isWindows() && !isLinux() && !isMac();
}