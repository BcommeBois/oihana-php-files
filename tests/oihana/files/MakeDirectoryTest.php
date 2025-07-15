<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

use PHPUnit\Framework\TestCase;

class MakeDirectoryTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('testDir', null, [
            'existing_writable_dir' => [],
            'existing_unwritable_dir' => [], // Un dossier qui sera rendu non accessible en écriture
        ]);

        $unwritableDir = $this->root->getChild('existing_unwritable_dir');

        // 1. We change the owner to a simulated non-root user.
        $unwritableDir->chown(vfsStream::OWNER_USER_1);

        // 2. We apply permissions that prohibit writing (r-xr-x---).
        // The test script is neither the owner nor in the group, so it has no rights.
        $unwritableDir->chmod(0550);
    }

    /**
     * Teste la création simple et réussie d'un répertoire.
     * @throws DirectoryException
     */
    public function testCreateDirectorySuccess(): void
    {
        $path = vfsStream::url('testDir/new_dir');
        $result = makeDirectory($path);

        $this->assertSame($path, $result);
        $this->assertTrue($this->root->hasChild('new_dir'));
        $this->assertTrue(is_dir($path));
    }

    public function testNullOrEmptyDirectoryPath():void
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('Directory path cannot be null or empty.');

        makeDirectory(null ) ;
        makeDirectory(''   ) ;
        makeDirectory('   ') ;
    }

    /**
     * @throws DirectoryException
     */
    public function testRecursiveOption():void
    {
        $recursivePath = vfsStream::url('testDir/recursive/option/test');
        $result = makeDirectory($recursivePath);
        $this->assertTrue(is_dir($result));

        $nonRecursivePath = vfsStream::url('testDir/nonrecursive/shouldfail');
        $this->expectException(DirectoryException::class);
        makeDirectory($nonRecursivePath, 0755, false);
    }

    /**
     * Teste la création d'un répertoire avec des permissions spécifiques.
     * @throws DirectoryException
     */
    public function testCreateDirectoryWithCustomPermissions(): void
    {
        $path = vfsStream::url('testDir/custom_perms_dir');
        makeDirectory($path, 0700);

        $this->assertTrue($this->root->hasChild('custom_perms_dir'));
        $this->assertEquals(0700, $this->root->getChild('custom_perms_dir')->getPermissions());
    }

    /**
     * Teste le cas où le répertoire existe déjà et est accessible en écriture.
     * @throws DirectoryException
     */
    public function testDirectoryAlreadyExistsAndIsWritable(): void
    {
        $path = vfsStream::url('testDir/existing_writable_dir');
        $result = makeDirectory($path);
        $this->assertSame($path, $result);
    }

    /**
     * Teste le cas où le répertoire existe déjà mais n'est pas accessible en écriture.
     */
    public function testDirectoryAlreadyExistsAndIsNotWritable(): void
    {
        $path = vfsStream::url('testDir/existing_unwritable_dir');
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage(sprintf('Directory "%s" is not writable.', $path));
        makeDirectory($path);
    }

    /**
     * Teste l'échec de la création à cause de permissions insuffisantes sur le parent.
     */
    public function testFailedToCreateDirectoryDueToParentPermissions(): void
    {
        $path = vfsStream::url('testDir/existing_unwritable_dir/new_subdir');
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage(sprintf('Failed to create directory "%s".', $path));
        makeDirectory($path);
    }

}