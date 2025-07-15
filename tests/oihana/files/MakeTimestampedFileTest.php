<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;

use PHPUnit\Framework\TestCase;

class MakeTimestampedFileTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        // Répertoire temporaire pour tests fichiers
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oihana_test';
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir);
        }
    }

    protected function tearDown(): void
    {
        // Nettoyer tous les fichiers créés dans tmpDir
        $files = glob($this->tmpDir . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
    }

    /**
     * @throws FileException
     */
    public function testCreateFileDefault()
    {
        $path = makeTimestampedFile(basePath: $this->tmpDir);

        $this->assertFileExists($path);
        $this->assertStringStartsWith($this->tmpDir, $path);
    }

    /**
     * @throws FileException
     */
    public function testCreateFileWithPrefixSuffixExtension()
    {
        $path = makeTimestampedFile
        (
            basePath : $this->tmpDir ,
            extension : '.txt' ,
            prefix : 'log_' ,
            suffix : '_end'
        );

        $this->assertFileExists($path);
        $this->assertStringContainsString('log_', $path);
        $this->assertStringContainsString('_end', $path);
        $this->assertStringEndsWith('.txt', $path);
    }

    /**
     * @throws FileException
     */
    public function testCreateFileWithSpecificDateAndFormat()
    {
        $date = '2025-12-01 14:00:00';
        $format = 'Ymd_His';
        $path = makeTimestampedFile(
            date : $date ,
            basePath : $this->tmpDir ,
            extension : '.log' ,
            format : $format
        );

        $expectedDateStr = date($format, strtotime($date));
        $this->assertStringContainsString($expectedDateStr, $path);
        $this->assertFileExists($path);
    }

    public function testMustExistTrueThrowsIfFileDoesNotExist()
    {
        $this->expectException(FileException::class);

        // On génère un chemin, mais ne touch pas le fichier, doit throw
        makeTimestampedFile(
            basePath: $this->tmpDir,
            mustExist: true
        );
    }

    /**
     * @throws FileException
     */
    public function testMustExistTruePassesIfFileExists()
    {
        // On crée un fichier manuellement
        $filePath = $this->tmpDir . DIRECTORY_SEPARATOR . 'existingfile.txt';
        file_put_contents($filePath, 'test');

        // On appelle la fonction avec mustExist = true et date correspondante pour générer le même nom
        // Pour simplifier, on utilise date "now" et on teste que l'assert passe (doit réussir)
        // Il faut passer manuellement le chemin exact, donc on contourne en appelant getTimestampedFile directement
        $resultPath = makeTimestampedFile(
            date : date('Y-m-d\TH:i:s') ,
            basePath : $this->tmpDir ,
            extension : '.txt' ,
            prefix : '' ,
            suffix : '' ,
            mustExist : false // false ici pour créer le fichier via touch()
        );

        $this->assertFileExists($resultPath);

        // Maintenant test mustExist = true sur ce fichier existant
        $result = makeTimestampedFile(
            date : date('Y-m-d\TH:i:s') ,
            basePath : $this->tmpDir ,
            extension : '.txt' ,
            prefix : '' ,
            suffix : '' ,
            mustExist : true
        );

        $this->assertEquals($resultPath, $result);
    }

    public function testThrowsWhenCannotCreateFile()
    {
        $this->expectException(FileException::class);

        // Essayer de créer un fichier dans un répertoire non accessible (ex: /root/)
        makeTimestampedFile(basePath: '/root/');
    }
}