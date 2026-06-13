<?php

namespace tests\oihana\files;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

use PHPUnit\Framework\TestCase;
use oihana\files\exceptions\DirectoryException;
use function oihana\core\date\formatDateTime;
use function oihana\files\deleteDirectory;
use function oihana\files\makeTimestampedDirectory;

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
        $expectedDirName = formatDateTime( timezone:'Europe/Paris' , format:'Y-m-d\TH:i:s' );
        $expectedPath = $basePath . DIRECTORY_SEPARATOR . $expectedDirName;

        mkdir($expectedPath);
        $this->assertTrue($this->root->hasChild($expectedDirName));

        $resultPath = makeTimestampedDirectory( basePath: $basePath ) ;

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

    /**
     * Regression guard for the `@mkdir()` suppression: a base path *under an existing
     * file* fails with `ENOTDIR`, which makes `mkdir()` emit a native
     * `mkdir(): Not a directory` warning before the typed exception. The other failure
     * tests use vfsStream, which does NOT surface that native warning — only a real
     * filesystem path does. Without the `@`, this test fails under `failOnWarning=true`.
     */
    public function testThrowsWithoutNativeWarningWhenBasePathIsUnderAFile(): void
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        {
            $this->markTestSkipped('ENOTDIR semantics are not reliable on Windows.');
        }

        $file = sys_get_temp_dir() . '/oihana_ts_enotdir_' . uniqid();
        file_put_contents($file, 'x');

        try
        {
            $this->expectException(DirectoryException::class);
            $this->expectExceptionMessage('Failed to creates a timestamped directory.');
            makeTimestampedDirectory(null, $file . '/sub');
        }
        finally
        {
            @unlink($file);
        }
    }

    /**
     * Avec un basePath vide, le répertoire est créé relativement au cwd
     * (branche ': $directoryName' du ternaire).
     * @throws DirectoryException
     */
    public function testEmptyBasePathCreatesRelativeDirectory(): void
    {
        $cwd = getcwd();
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana_ts_' . uniqid();
        mkdir($tmp);
        chdir($tmp);

        try
        {
            $result = makeTimestampedDirectory(null, '', 'ts_');

            // basePath vide -> le chemin retourné est le seul nom de répertoire (sans séparateur).
            $this->assertStringNotContainsString(DIRECTORY_SEPARATOR, $result);
            $this->assertDirectoryExists($tmp . DIRECTORY_SEPARATOR . $result);
        }
        finally
        {
            chdir($cwd);
            deleteDirectory($tmp, assertable: false);
        }
    }
}