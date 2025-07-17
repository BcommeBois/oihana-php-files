<?php

namespace oihana\files\archive\tar;

/**
 * Indicates if the tar file is compressed.
 */
function tarIsCompressed( string $tarFile ) :bool
{
    return preg_match('/\\.tar\\.(gz|bz2)$/i', $tarFile);
}
