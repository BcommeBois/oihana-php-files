<?php

namespace oihana\files\path ;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversFunction('oihana\files\paths\isRelativePath')]
final class IsRelativePathTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function testIsRelativePath( string $path , bool $expected , string $message) : void
    {
        $this->assertSame( $expected, isRelativePath( $path ) , $message ) ;
    }

    /**
     * Fournit une liste de chemins à tester avec leur résultat attendu.
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function pathProvider(): array
    {
        return
        [
            'HTTP URL' => ['http://example.com/foo', true, 'Une URL HTTP ne devrait pas être considérée comme un chemin absolu.'],
            'Empty string' => ['', true, 'Une chaîne vide ne devrait pas être absolue.'],
            'Simple filename' => ['file.txt', true, 'Un simple nom de fichier est relatif.'],
            'Relative path' => ['documents/report.pdf', true, 'Un chemin sans racine est relatif.'],
            'Parent directory path' => ['../src/components', true, 'Un chemin avec .. est relatif.'],
            'Current directory path' => ['./config.json', true, 'Un chemin avec ./ est relatif.'],
            'Windows path without drive letter' => ['Windows\\System32', true, 'Un chemin Windows sans lettre de lecteur est relatif.'],
            'Path starting with a number' => ['1:/data', true, 'Un chemin ne peut pas commencer par un chiffre pour être absolu.'],
            'Incomplete drive letter' => ['C', true, 'Une lettre seule sans les deux-points n\'est pas absolue.'],
            'Just a scheme' => ['ftp://', true, 'Un schéma seul sans chemin ne devrait pas être considéré comme absolu.'],

            'Unix root' => ['/', false, 'Un simple slash devrait être absolu.'],
            'Unix path' => ['/var/www/html', false, 'Un chemin Unix standard devrait être absolu.'],
            'Windows drive letter with colon' => ['C:', false, 'Une lettre de lecteur Windows seule est absolue.'],
            'Windows path with backslashes' => ['C:\\Windows\\System32', false, 'Un chemin Windows avec antislashs est absolu.'],
            'Windows path with forward slashes' => ['D:/Program Files/My App', false, 'Un chemin Windows avec slashs est absolu.'],
            'Windows root on current drive' => ['\\Users\\Public', false, 'Un chemin commençant par \\ est absolu.'],
            'Windows network path' => ['\\\\SERVER\\share', false, 'Un chemin réseau Windows est absolu.'],
            'Path with file scheme (Unix)' => ['file:///home/user/doc.txt', false, 'Un chemin avec le schéma file:// est absolu.'],
            'Path with file scheme (Windows)' => ['file:///C:/Users/user', false, 'Un chemin Windows avec le schéma file:// est absolu.'],
        ];
    }
}