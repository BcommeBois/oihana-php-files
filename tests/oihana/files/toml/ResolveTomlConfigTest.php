<?php

namespace oihana\files\toml;

use PHPUnit\Framework\TestCase;

use oihana\files\exceptions\FileException;
use oihana\files\exceptions\DirectoryException;
use Devium\Toml\TomlError;
use function oihana\files\deleteDirectory;
use function oihana\files\makeDirectory;

class ResolveTomlConfigTest extends TestCase
{
    private string $tempDir;

    /**
     * @throws DirectoryException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/oihana/files/toml/toml_test_' . uniqid() ;
        makeDirectory( $this-> tempDir);
    }

    /**
     * @throws DirectoryException
     */
    protected function tearDown(): void
    {
        deleteDirectory( $this->tempDir );
    }

    /**
     * @throws TomlError
     * @throws FileException
     * @throws DirectoryException
     */
    public function testReturnsDefaultConfigIfFilePathNullOrEmpty(): void
    {
        $default = ['key' => 'value'];
        $result = resolveTOMLConfig(null, $default);
        $this->assertSame($default, $result);

        $result = resolveTOMLConfig('', $default);
        $this->assertSame($default, $result);
    }

    /**
     * @throws DirectoryException
     * @throws TomlError
     */
    public function testThrowsFileExceptionIfFileDoesNotExist(): void
    {
        $this->expectException(FileException::class);
        resolveTOMLConfig('nonexistentfile');
    }

    /**
     * @throws FileException
     * @throws TomlError
     */
    public function testThrowsDirectoryExceptionIfDefaultPathInvalid(): void
    {
        $invalidDir = $this->tempDir . '/not_a_dir';

        $this->expectException(DirectoryException::class);

        // Pass relative file + invalid base path
        resolveTOMLConfig('config.toml', [], $invalidDir);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws TomlError
     */
    public function testLoadsAndMergesTomlFile(): void
    {
        $filePath = $this->tempDir . '/config.toml';

        $tomlContent = <<<TOML
[section]
foo = "bar"
number = 42
TOML;

        file_put_contents($filePath, $tomlContent);

        // Mock toml_decode to return an array
        // Since toml_decode is a global function, you might need to wrap it or mock differently.
        // For demo, let's assume toml_decode works as expected.

        $defaultConfig = ['section' => ['foo' => 'default', 'extra' => 'keep']];
        $result = resolveTOMLConfig($filePath, $defaultConfig);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('section', $result);
        $this->assertSame('bar', $result['section']['foo']);
        $this->assertSame('keep', $result['section']['extra']);
        $this->assertSame(42, $result['section']['number']);
    }

    /**
     * @throws DirectoryException
     * @throws FileException
     * @throws TomlError
     */
    public function testAddsTomlExtensionIfMissing(): void
    {
        $filePath = $this->tempDir . '/myconfig.toml';

        file_put_contents($filePath, 'key = "value"');

        $defaultConfig = ['key' => 'default'];

        $result = resolveTOMLConfig(rtrim($filePath, '.toml'), $defaultConfig);

        $this->assertSame('value', $result['key']);
    }
}