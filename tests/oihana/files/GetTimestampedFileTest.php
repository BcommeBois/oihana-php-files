<?php

namespace oihana\files ;

use oihana\files\exceptions\FileException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function preg_quote;

class GetTimestampedFileTest extends TestCase
{
    /** @return iterable<string, array{args: array, expectedPath: string}] */
    public static function validCases(): iterable
    {
        yield 'default‑now' =>
        [
            'args' => [],
            'expectedPath' => '~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$~', // matches “now”
        ];

        yield 'base prefix suffix ext' =>
        [
            'args' => [
                'date'      => '2025-12-01 14:00:00',
                'basePath'  => '/tmp',
                'extension' => '.sql',
                'prefix'    => 'backup_',
                'suffix'    => '-hello',
            ],
            'expectedPath' => '~^/tmp/backup_2025-12-01T14:00:00-hello\.sql$~',
        ];

        yield 'custom format no base' =>
        [
            'args' => [
                'date'     => '2025-07-15 08:45:33',
                'extension'=> '.txt',
                'prefix'   => 'log_',
                'timezone' => 'UTC',
                'format'   => 'Ymd_His',
            ],
            'expectedPath' => '~^log_20250715_084533\.txt$~',
        ];
    }

    /**
     * @throws FileException
     */
    #[DataProvider('validCases')]
    public function testGeneratesExpectedPath( array $args, string $expectedPath ): void
    {
        $path = getTimestampedFile( ...$args , assertable: false );
        self::assertNotNull( $path ) ;
        self::assertMatchesRegularExpression($expectedPath, $path);
    }

    public function testThrowsWhenAssertFileFails(): void
    {
        $this->expectException(FileException::class);
        getTimestampedFile( basePath: '/root/forbidden' );
    }
}