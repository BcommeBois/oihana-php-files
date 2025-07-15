<?php

namespace oihana\files ;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

use PHPUnit\Framework\TestCase;
use oihana\files\exceptions\DirectoryException;
use function oihana\core\date\formatDateTime;

class MakeTimestampedDirectoryTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('home');
    }

    /**
     * Teste la création d'un répertoire avec les paramètres par défaut.
     * Le timestamp est fourni par le stub de formatDateTime.
     * @throws DirectoryException
     */
    public function testCreatesDirectoryWithDefaultParameters(): void
    {
        $basePath = $this->root->url();
        $resultPath = makeTimestampedDirectory(null, $basePath);

        // 1. Extraire le nom du répertoire créé
        $dirName = basename($resultPath);

        // 2. Vérifier que le format est correct (AAAA-MM-JJTHH:MM:SS)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $dirName);

        // 3. Vérifier que le répertoire existe bien
        $this->assertTrue($this->root->hasChild($dirName));
    }

    /**
     * Teste la création avec tous les paramètres personnalisés.
     * Ce test reste inchangé car la date est fixe et le résultat est prédictible.
     * @throws DirectoryException
     */
    public function testCreatesDirectoryWithAllParameters(): void
    {
        $basePath = $this->root->url();
        $date = '2024-01-10 15:45:00';
        $prefix = 'archive-';
        $suffix = '-final';
        $format = 'Ymd_His';
        $timezone = 'America/New_York';

        $expectedDirName = 'archive-20240110_154500-final';
        $expectedPath = $basePath . DIRECTORY_SEPARATOR . $expectedDirName;

        $resultPath = makeTimestampedDirectory($date, $basePath, $prefix, $suffix, $timezone, $format);

        $this->assertSame($expectedPath, $resultPath);
        $this->assertTrue($this->root->hasChild($expectedDirName));
    }

    /**
     * Teste que la fonction ne lève pas d'erreur si le répertoire existe déjà.
     * @return void
     * @throws DirectoryException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function testDoesNotThrowErrorIfDirectoryExists(): void
    {
        $basePath = $this->root->url();
        // On génère le nom attendu en utilisant la VRAIE fonction formatDateTime
        $expectedDirName = formatDateTime();
        $expectedPath = $basePath . DIRECTORY_SEPARATOR . $expectedDirName;

        // Crée le répertoire manuellement avant l'appel
        mkdir($expectedPath);
        $this->assertTrue($this->root->hasChild($expectedDirName));

        $resultPath = makeTimestampedDirectory(null, $basePath);

        $this->assertSame($expectedPath, $resultPath);
    }

    /**
     * Teste que les chemins de base inexistants sont créés récursivement.
     * @throws DirectoryException
     */
    public function testCreatesNestedBasePath(): void
    {
        $nestedPath = $this->root->url() . '/data/backups';

        $resultPath = makeTimestampedDirectory(null, $nestedPath);
        $dirName = basename($resultPath);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $dirName);
        $this->assertTrue($this->root->hasChild('data/backups/' . $dirName));
    }

    /**
     * Teste qu'une exception est levée si la date est invalide.
     * (Mis à jour selon le vrai comportement de formatDateTime)
     */
    public function testThrowsExceptionOnInvalidDate(): void
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('Failed to creates a timestamped directory.');

        makeTimestampedDirectory('ceci n\'est pas une date', $this->root->url());
    }

    /**
     * Teste la levée d'une exception si mkdir() échoue.
     * Ce test reste inchangé car il teste les permissions du système de fichiers.
     */
    public function testThrowsExceptionOnMkdirFailure(): void
    {
        $this->expectException(DirectoryException::class);
        $this->expectExceptionMessage('Failed to creates a timestamped directory.');

        // Rend le répertoire racine non inscriptible
        $this->root->chmod(0444);

        makeTimestampedDirectory(null, $this->root->url());
    }
}