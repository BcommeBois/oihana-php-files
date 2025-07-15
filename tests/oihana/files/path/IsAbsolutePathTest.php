<?php

namespace oihana\files\path ;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(isAbsolutePath::class)]
final class IsAbsolutePathTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function testIsAbsolutePath(string $path, bool $expected, string $message): void
    {
        $this->assertSame( $expected, isAbsolutePath( $path ) , $message ) ;
    }

    /**
     * Fournit une liste de chemins à tester avec leur résultat attendu.
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function pathProvider(): array
    {
        return
        [
            'Unix root' => ['/', true, 'Un simple slash devrait être absolu.'],
            'Unix path' => ['/var/www/html', true, 'Un chemin Unix standard devrait être absolu.'],
            'Windows drive letter with colon' => ['C:', true, 'Une lettre de lecteur Windows seule est absolue.'],
            'Windows path with backslashes' => ['C:\\Windows\\System32', true, 'Un chemin Windows avec antislashs est absolu.'],
            'Windows path with forward slashes' => ['D:/Program Files/My App', true, 'Un chemin Windows avec slashs est absolu.'],
            'Windows root on current drive' => ['\\Users\\Public', true, 'Un chemin commençant par \\ est absolu.'],
            'Windows network path' => ['\\\\SERVER\\share', true, 'Un chemin réseau Windows est absolu.'],
            'Path with file scheme (Unix)' => ['file:///home/user/doc.txt', true, 'Un chemin avec le schéma file:// est absolu.'],
            'Path with file scheme (Windows)' => ['file:///C:/Users/user', true, 'Un chemin Windows avec le schéma file:// est absolu.'],

            'HTTP URL' => ['http://example.com/foo', false, 'Une URL HTTP ne devrait pas être considérée comme un chemin absolu.'],
            'Empty string' => ['', false, 'Une chaîne vide ne devrait pas être absolue.'],
            'Simple filename' => ['file.txt', false, 'Un simple nom de fichier est relatif.'],
            'Relative path' => ['documents/report.pdf', false, 'Un chemin sans racine est relatif.'],
            'Parent directory path' => ['../src/components', false, 'Un chemin avec .. est relatif.'],
            'Current directory path' => ['./config.json', false, 'Un chemin avec ./ est relatif.'],
            'Windows path without drive letter' => ['Windows\\System32', false, 'Un chemin Windows sans lettre de lecteur est relatif.'],
            'Path starting with a number' => ['1:/data', false, 'Un chemin ne peut pas commencer par un chiffre pour être absolu.'],
            'Incomplete drive letter' => ['C', false, 'Une lettre seule sans les deux-points n\'est pas absolue.'],
            'Just a scheme' => ['ftp://', false, 'Un schéma seul sans chemin ne devrait pas être considéré comme absolu.'],
        ];
    }
}