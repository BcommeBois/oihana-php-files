<?php

namespace oihana\files\archive\tar ;

/**
 * The environment every `tar` this library runs is given.
 *
 * Two variables, and each one is there for a measured reason.
 *
 * **`LC_ALL=C`** so the output keeps one shape. `tar -tv` prints a date column, and
 * {@see untarEntries()} reads the member name as *everything after the time* — a locale
 * that spells dates differently would make that parse fail on a sound archive.
 *
 * **`PATH`, passed through from this process**, because `proc_open()` given an
 * environment **replaces** the whole of it rather than adding to it. And `tar` compresses
 * nothing itself: `--xz` makes it spawn `xz` through a shell. With no `PATH`, that shell
 * falls back to a built-in default — `/usr/bin:/bin` on a Mac — so `gzip` and `bzip2`
 * were found and **`xz` and `zstd`, installed by Homebrew under `/opt/homebrew/bin`, were
 * not**. Measured on 2026-10-24:
 *
 * ```
 * /bin/sh: xz: command not found
 * tar: Child returned status 127
 * ```
 *
 * …on a host where `tarCompressorExists()` had just found `xz` on this process's own
 * `PATH` and answered yes. The check looked at one environment and the work ran in
 * another. That it went unnoticed until now is only because `tar()` refused `xz` before
 * ever reaching the binary.
 *
 * @return array<string,string>
 *
 * @package oihana\files\archive\tar
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.5.0
 */
function tarProcessEnvironment() :array
{
    $path = getenv( 'PATH' ) ;

    return $path === false
        ? [ 'LC_ALL' => 'C' ]
        : [ 'LC_ALL' => 'C' , 'PATH' => $path ] ;
}
