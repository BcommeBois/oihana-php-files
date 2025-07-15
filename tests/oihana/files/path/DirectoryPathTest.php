<?php

namespace oihana\files\path ;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\TestCase;

#[CoversFunction('oihana\files\paths\directoryPath')]
final class DirectoryPathTest extends TestCase
{
    public function testUnixStylePaths()
    {
        // Standard Unix Path
        $this->assertSame('/var/www/html', directoryPath('/var/www/html/file.txt'));

        // Test pour un fichier dans le répertoire racine
        $this->assertSame('/', directoryPath('/file.txt'));
    }

    public function testWindowsStylePaths()
    {
        // Test pour un chemin Windows avec des barres inverses
        $this->assertSame('C:\Windows\System32', directoryPath('C:\Windows\System32\file.txt'));

        // Test pour un chemin Windows avec des barres obliques
        $this->assertSame('D:/Program Files/My App', directoryPath('D:/Program Files/My App/file.txt'));

        // Test pour un fichier dans la racine d'un lecteur Windows
        $this->assertSame('C:/', directoryPath('C:/file.txt'));
    }

    public function testPathsWithUriSchemes()
    {
        // Test pour un chemin avec un schéma URI
        $this->assertSame('/home/user', directoryPath('file:///home/user/doc.txt'));
    }

    public function testEdgeCases()
    {
        // Test pour un fichier sans répertoire
        $this->assertSame('', directoryPath('file.txt'));

        // Test pour un chemin vide
        $this->assertSame('', directoryPath(''));
    }

    public function testUncPaths()
    {
        $this->assertSame(
            '\\\\NAS-01\\media',
            directoryPath('\\\\NAS-01\\media\\photos\\vacances.jpg')
        );
    }
}