<?php

namespace oihana\files\helpers ;

/**
 * The internal canonicalizePath static buffer helper.
 */
class CanonicalizeBuffer
{
    /**
     * The number of buffer entries that triggers a cleanup operation.
     */
    public const int CLEANUP_THRESHOLD = 1250 ;

    /**
     * The buffer size after the cleanup operation.
     */
    public const int CLEANUP_SIZE = 1000 ;

    /**
     * Buffers input/output of {@link canonicalizePath()}.
     * @var array<string, string>
     */
    public static array $buffer = [] ;

    /**
     * The buffer size.
     * @var int
     */
    public static int $bufferSize = 0 ;
}