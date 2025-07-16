<?php

namespace oihana\files ;

use oihana\files\exceptions\DirectoryException;
use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\FileException;
use oihana\files\enums\FileMimeType;

class ValidateMimeTypeTest extends TestCase
{
    private string $tempDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/validateMimeTypeTest_' . uniqid();
        makeDirectory( $this->tempDir ) ;
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tempDir ) ;
    }

    /**
     * Crée un fichier temporaire avec un contenu spécifique pour simuler un type MIME
     */
    private function createTempFile(string $content, string $extension = '.txt'): string
    {
        $filename = $this->tempDir . '/test_' . uniqid() . $extension;
        file_put_contents($filename, $content);
        return $filename;
    }

    /**
     * Test de validation réussie avec un seul type MIME autorisé
     * @throws FileException
     */
    public function testValidateMimeTypeSuccessWithSingleAllowedType(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        // Validation avec le type MIME correct
        $this->expectNotToPerformAssertions();
        validateMimeType($file, ['text/plain']);
    }

    /**
     * Test de validation réussie avec plusieurs types MIME autorisés
     * @throws FileException
     */
    public function testValidateMimeTypeSuccessWithMultipleAllowedTypes(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        // Validation avec plusieurs types MIME dont le correct
        $this->expectNotToPerformAssertions();
        validateMimeType($file, ['image/jpeg', 'text/plain', 'application/pdf']);
    }

    /**
     * Test de validation échouée avec type MIME non autorisé
     */
    public function testValidateMimeTypeFailsWithUnallowedType(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Invalid MIME type for file');
        $this->expectExceptionMessage('Expected one of [image/jpeg, application/pdf]');
        $this->expectExceptionMessage('but got "text/plain"');

        validateMimeType($file, ['image/jpeg', 'application/pdf']);
    }

    /**
     * Test avec des types MIME contenant des arrays (comme FileMimeType::M4A)
     * @throws FileException
     */
    public function testValidateMimeTypeWithArrayMimeTypes(): void
    {
        // Crée un fichier texte pour simuler
        $file = $this->createTempFile('Hello World', '.txt');

        // Test avec des arrays de types MIME
        $allowedTypes = [
            ['audio/mp4', 'audio/x-m4a'], // Simule FileMimeType::M4A
            'text/plain'
        ];

        $this->expectNotToPerformAssertions();
        validateMimeType($file, $allowedTypes);
    }

    /**
     * Test avec des types MIME contenant des arrays mais le fichier n'est pas autorisé
     */
    public function testValidateMimeTypeFailsWithArrayMimeTypes(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        $allowedTypes = [
            ['audio/mp4', 'audio/x-m4a'], // Simule FileMimeType::M4A
            'image/jpeg'
        ];

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Invalid MIME type for file');
        $this->expectExceptionMessage('Expected one of [audio/mp4, audio/x-m4a, image/jpeg]');

        validateMimeType($file, $allowedTypes);
    }

    /**
     * Test de la normalisation case-insensitive
     * @throws FileException
     */
    public function testValidateMimeTypeCaseInsensitive(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        // Test avec des types MIME en différentes casses
        $this->expectNotToPerformAssertions();
        validateMimeType($file, ['TEXT/PLAIN', 'Image/JPEG']);
    }

    /**
     * Test avec des types MIME dupliqués
     * @throws FileException
     */
    public function testValidateMimeTypeWithDuplicates(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        // Test avec des types MIME dupliqués
        $this->expectNotToPerformAssertions();
        validateMimeType($file, ['text/plain', 'text/plain', 'TEXT/PLAIN']);
    }

    /**
     * Test avec un fichier dont le type MIME ne peut pas être déterminé
     */
    public function testValidateMimeTypeWithUndetectableFile(): void
    {
        // Crée un fichier avec un contenu binaire bizarre
        $file = $this->createTempFile('', '.unknown');

        $this->expectException(FileException::class);
        $this->expectExceptionMessage( 'Invalid MIME type for file'    );
        $this->expectExceptionMessage( 'Expected one of [text/plain]'  );
        $this->expectExceptionMessage( 'but got "application/x-empty"' );

        validateMimeType( $file , ['text/plain'] );
    }

    public function testValidateMimeTypeWithInvalidFilePath(): void
    {
        $file = '/path/to/nonexistent/file';

        $this->expectException( FileException::class );
        $this->expectExceptionMessage('Unable to determine MIME type for file');

        validateMimeType($file, ['text/plain']);
    }

    /**
     * Test avec une liste vide de types MIME autorisés
     */
    public function testValidateMimeTypeWithEmptyAllowedTypes(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Invalid MIME type for file');
        $this->expectExceptionMessage('Expected one of []');

        validateMimeType($file, []);
    }

    /**
     * Test avec des constantes FileMimeType réelles
     * @throws FileException
     */
    public function testValidateMimeTypeWithFileMimeTypeConstants(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        // Test avec des constantes réelles de FileMimeType
        $allowedTypes = [
            FileMimeType::TXT,
            FileMimeType::PDF,
            FileMimeType::M4A // Array constant
        ];

        $this->expectNotToPerformAssertions();
        validateMimeType($file, $allowedTypes);
    }

    /**
     * Test avec des constantes FileMimeType qui ne correspondent pas
     */
    public function testValidateMimeTypeFailsWithFileMimeTypeConstants(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        $allowedTypes = [
            FileMimeType::PDF,
            FileMimeType::JPEG,
            FileMimeType::MP3
        ];

        $this->expectException(FileException::class);
        $this->expectExceptionMessage('Invalid MIME type for file');
        $this->expectExceptionMessage('but got "text/plain"');

        validateMimeType($file, $allowedTypes);
    }

    /**
     * Test with a mix of strings and strings[]
     * @throws FileException
     */
    public function testValidateMimeTypeWithMixedTypes(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        $allowedTypes = [
            'text/plain',
            ['audio/mp4', 'audio/x-m4a'],
            'image/jpeg',
            ['application/javascript', 'text/javascript']
        ];

        $this->expectNotToPerformAssertions();
        validateMimeType($file, $allowedTypes);
    }

    /**
     * Test de performance avec un grand nombre de types MIME
     * @throws FileException
     */
    public function testValidateMimeTypePerformanceWithManyTypes(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        // Crée une liste avec beaucoup de types MIME
        $allowedTypes = [];
        for ($i = 0; $i < 100; $i++)
        {
            $allowedTypes[] = "application/test-{$i}";
        }
        $allowedTypes[] = 'text/plain'; // Ajoute le type correct à la fin

        $start = microtime(true);
        validateMimeType($file, $allowedTypes);
        $end = microtime(true);

        // Vérifie que la validation ne prend pas trop de temps
        $this->assertLessThan(0.1, $end - $start, 'Validation should be fast even with many MIME types');
    }

    /**
     * Test avec des types MIME contenant des caractères spéciaux
     * @throws FileException
     */
    public function testValidateMimeTypeWithSpecialCharacters(): void
    {
        // Crée un fichier texte
        $file = $this->createTempFile('Hello World', '.txt');

        $allowedTypes = [
            'text/plain',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel'
        ];

        $this->expectNotToPerformAssertions();
        validateMimeType($file, $allowedTypes);
    }

    /**
     * Test du message d'erreur détaillé
     */
    public function testValidateMimeTypeErrorMessageContent(): void
    {
        $file = $this->createTempFile('Hello World', '.txt');

        try
        {
            validateMimeType($file, ['image/jpeg', 'application/pdf']);
            $this->fail('Expected FileException was not thrown');
        }
        catch (FileException $e) {
            $message = $e->getMessage();

            // Vérifie que le message contient les informations attendues
            $this->assertStringContainsString('Invalid MIME type for file', $message);
            $this->assertStringContainsString($file, $message);
            $this->assertStringContainsString('Expected one of', $message);
            $this->assertStringContainsString('image/jpeg', $message);
            $this->assertStringContainsString('application/pdf', $message);
            $this->assertStringContainsString('but got', $message);
            $this->assertStringContainsString('text/plain', $message);
        }
    }
}