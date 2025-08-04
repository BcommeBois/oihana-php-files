<?php

namespace oihana\files\options;

use oihana\abstracts\Options;
use oihana\enums\Char;

/**
 * Enumeration class that defines various options to use in the makeFile function.
 *
 * @package oihana\files\enums
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class MakeFileOptions extends Options
{
    /**
     * If true, appends content instead of overwriting. Default: false.
     */
    public bool $append = false ;

    /**
     * The content of the robots.txt file.
     * @var string|null
     */
    public ?string $content ;

    /**
     * Set the robots.txt file path.
     * @var string|null
     */
    public ?string $file = null ;

    /**
     * If true, creates parent directories if they do not exist. Default: true.
     */
    public bool $force = true ;

    /**
     * Set the group of the file.
     * @var string|null
     */
    public ?string $group = null ;

    /**
     * If true, creates parent directories if they do not exist. Default: true.
     */
    public bool $lock = true ;

    /**
     * If true, overwrites existing files. Default: true.
     * @var bool|null
     */
    public ?bool $overwrite = true ;

    /**
     * Set the owner of the file.
     * @var string|null
     */
    public ?string $owner = null ;

    /**
     * File permissions to set (octal). Default: 0644.
     * @var int|null
     */
    public ?int $permissions = null ;

    /**
     * Returns the string expression of the object.
     * @return string
     */
    public function __toString() : string
    {
        return $this->file ?? Char::EMPTY ;
    }
}