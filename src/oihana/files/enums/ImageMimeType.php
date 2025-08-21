<?php

namespace oihana\files\enums;

use oihana\files\enums\traits\ImageMimeTypeTrait;
use oihana\reflect\traits\ConstantsTrait;

/**
 * Enumeration that defines various image mime-types supported by the application.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class ImageMimeType
{
    use ConstantsTrait ,
        ImageMimeTypeTrait ;
}