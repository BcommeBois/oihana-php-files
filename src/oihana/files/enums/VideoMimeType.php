<?php

namespace oihana\files\enums;

use oihana\files\enums\traits\VideoMimeTypeTrait;
use oihana\reflect\traits\ConstantsTrait;

/**
 * Enumeration that defines various video mime-types supported by the application.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class VideoMimeType
{
    use ConstantsTrait ,
        VideoMimeTypeTrait ;
}