<?php

namespace oihana\files\enums;

use oihana\reflections\traits\ConstantsTrait;

class FindMode
{
    use ConstantsTrait ;

    /**
     * List files and directories.
     */
    public const string BOTH  = 'both' ;

    /**
     * List files only.
     */
    public const string FILES = 'files' ;

    /**
     * List directories only.
     */
    public const string DIRS  = 'dirs' ;

}