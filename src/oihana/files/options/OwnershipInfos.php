<?php

namespace oihana\files\options;

use oihana\options\Options;

/**
 * Represents the ownership information of a file or directory.
 *
 * Includes both numeric (UID, GID) and textual (owner, group) identifiers.
 *
 * This object can be used for comparison, validation, or debugging when
 * managing file permissions and ownerships (e.g. in `makeFile`, `chown`, etc.).
 *
 * @package oihana\files\options
 * @author  Marc Alcaraz (ekameleon)
 * @since   1.0.0
 */
class OwnershipInfos extends Options
{
    /**
     * The file owner's name (e.g. 'www-data').
     * @var string|null
     */
    public ?string $owner = null ;

    /**
     * The file group's name (e.g. 'www-data').
     * @var string|null
     */
    public ?string $group = null ;

    /**
     * The file owner's UID.
     * @var int|null
     */
    public ?int $uid = null ;

    /**
     * The file group's GID.
     * @var int|null
     */
    public ?int $gid = null ;

    /**
     * Compares this OwnershipInfo object with another to determine if they are identical.
     *
     * The comparison checks for strict equality between the UID, GID,
     * owner name, and group name of both objects.
     *
     * @param OwnershipInfos $other The OwnershipInfo instance to compare against.
     *
     * @return bool Returns true if all ownership fields (UID, GID, owner, group) match exactly; false otherwise.
     */
    public function equalsTo( OwnershipInfos $other ): bool
    {
        return $this->uid   === $other->uid
            && $this->gid   === $other->gid
            && $this->owner === $other->owner
            && $this->group === $other->group;
    }

    /**
     * Returns a string representation of the ownership.
     * @return string Format: "owner:group (uid:gid)"
     */
    public function __toString(): string
    {
        return sprintf( '%s:%s (%s:%s)', $this->owner ?? '?', $this->group ?? '?', $this->uid ?? '?', $this->gid ?? '?' ) ;
    }
}