<?php

namespace oihana\files\enums;

use oihana\files\enums\traits\AudioMimeTypeTrait;
use oihana\reflect\traits\ConstantsTrait;

/**
 * Enumeration that defines various audio mime-types supported by the application.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class AudioMimeType
{
    use ConstantsTrait ,
        AudioMimeTypeTrait ;
}