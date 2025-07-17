<?php

/**
 * Returns detailed reflection information about a given function.
 *
 * This function uses PHP's Reflection API to retrieve metadata about
 * a function specified by its name, optionally including its namespace.
 *
 * @param callable|string $callable The callable to reflect: function name, closure, or method.
 *
 * @return array|null Returns an associative array of function details if the function exists, or null otherwise.
 *                    The array includes:
 *                    - 'name'       : Full function name including namespace.
 *                    - 'namespace'  : Namespace the function belongs to.
 *                    - 'alias'      : Short function name without namespace.
 *                    - 'file'       : Path to the file where the function is defined.
 *                    - 'startLine'  : The starting line number of the function definition.
 *                    - 'endLine'    : The ending line number of the function definition.
 *                    - 'isInternal' : Whether the function is internal to PHP.
 *                    - 'isUser'     : Whether the function is user-defined.
 *                    - 'comment'    : The function's docblock comment, or null if none.
 */
function getFunctionReflectionInfo( callable|string $callable ) : ?array
{
    try {
        if ( is_string( $callable ) )
        {
            // Static or instance method as "Class::method"
            if ( str_contains( $callable , '::' ) )
            {
                $ref = new ReflectionMethod($callable);
            }
            else
            {
                if ( !function_exists( $callable ) ) // Function name
                {
                    return null;
                }
                $ref = new ReflectionFunction($callable);
            }
        }
        elseif ( is_array( $callable ) && count( $callable ) === 2 )
        {
            $ref = new ReflectionMethod($callable[0], $callable[1]) ; // Method as [object|string, method]
        }
        elseif ( $callable instanceof Closure )
        {
            $ref = new ReflectionFunction( $callable );     // Closure
        }
        else
        {
            return null ; // Unknown type
        }

        $name = $ref->getName();
        $namespace = $ref instanceof ReflectionMethod
            ? $ref->getDeclaringClass()->getNamespaceName()
            : $ref->getNamespaceName();

        return
        [
            'name'       => $ref instanceof ReflectionMethod ? $ref->getDeclaringClass()->getName() . '::' . $ref->getName() : $name,
            'namespace'  => $namespace,
            'alias'      => $ref->getShortName() ,
            'file'       => $ref->getFileName() ,
            'startLine'  => $ref->getStartLine() ,
            'endLine'    => $ref->getEndLine() ,
            'isInternal' => $ref->isInternal() ,
            'isUser'     => $ref->isUserDefined() ,
            'comment'    => $ref->getDocComment() ?: null
        ];
    }
    catch ( ReflectionException $e )
    {
        return null;
    }
}