<?php

namespace oihana\files\enums;

use oihana\reflections\traits\ConstantsTrait;

/**
 * Enumeration of all ownership information components.
 *
 * @package oihana\files\options
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class OwnershipInfo
{
    use ConstantsTrait ;

    public const string GROUP = 'group' ;
    public const string GID   = 'gid' ;
    public const string OWNER = 'owner' ;
    public const string UID   = 'uid' ;
}