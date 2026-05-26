<?php

namespace oihana\files ;

/**
 * Checks if a file path should be excluded based on an array of patterns.
 *
 * This function supports two types of patterns:
 * 1. Glob patterns (e.g., '*.log', 'config/*.php'). These are matched using fnmatch().
 * 2. PCRE regular expressions (e.g., '/^temp-\d+\.tmp$/i'). The function auto-detects
 * regex patterns by checking if they are enclosed in matching delimiter characters.
 *
 * For each pattern, the function attempts to match it against both the full file path
 * and the basename of the file. The match is successful if any pattern matches.
 * The glob matching is performed with the FNM_PATHNAME flag, meaning wildcards
 * will not match directory separators (/).
 *
 * @param string   $filePath        The absolute or relative path to the file to check.
 * @param string[] $excludePatterns An array of glob or PCRE patterns.
 *
 * @return bool Returns `true` if the file path matches any of the exclusion patterns, `false` otherwise.
 *
 * @security
 * Each pattern is evaluated with `fnmatch()` (glob) or `preg_match()` (regex,
 * when the pattern is enclosed in matching delimiters). PHP's regex engine has
 * no execution timeout, so a maliciously crafted regex such as `/^(a+)+$/` can
 * cause catastrophic backtracking and effectively a CPU DoS. **Patterns must
 * come from a trusted source** (configuration, internal code) — never from
 * direct user input. See the
 * [security guide](../../../wiki/en/security.md#redos-on-user-supplied-regex-patterns).
 *
 * @example
 * ```php
 * $patterns = [
 * '*.log',          // Exclude all .log files (matches basename)
 * '/^error_\d+/',   // Exclude files starting with error_... (regex)
 * 'config/db.php'   // Exclude a specific file path (matches path suffix)
 * ];
 *
 * // Returns true (matches '*.log' on basename)
 * shouldExcludeFile('/var/www/app/logs/access.log', $patterns);
 *
 * // Returns true (matches regex on basename)
 * shouldExcludeFile('/tmp/error_12345.txt', $patterns);
 *
 * // Returns true (fnmatch matches '*config/db.php' against the full path)
 * shouldExcludeFile('/var/www/app/config/db.php', $patterns);
 *
 * // Returns false (no pattern matches)
 * shouldExcludeFile('/var/www/index.php', $patterns);
 * ```
 *
 * @package oihana\files
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
function shouldExcludeFile( string $filePath , array $excludePatterns ) :bool
{
    $filename = basename($filePath);

    return array_any($excludePatterns, function ( string $pattern) use ( $filename , $filePath ) :string
    {
        if ( preg_match('/^(.).+\1[imsxuADU]*$/', $pattern ) )
        {
            return preg_match($pattern, $filePath) === 1
                || preg_match($pattern, $filename) === 1;
        }

        return fnmatch($pattern, $filePath, FNM_PATHNAME)
            || fnmatch($pattern, $filename, FNM_PATHNAME);
    });
}
