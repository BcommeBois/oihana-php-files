<?php

namespace tests\oihana\options;

use InvalidArgumentException;
use ReflectionException;

use oihana\enums\Char;
use oihana\options\Options;

use PHPUnit\Framework\TestCase;

use tests\oihana\options\mocks\MockOption;
use tests\oihana\options\mocks\MockOptions;
use tests\oihana\options\mocks\TestOptions;


class OptionsTest extends TestCase
{
    public function getConcreteOptionsInstance(array|object|null $init = null): Options
    {
        return new MockOptions( $init ) ;
    }

    public function testCreateWithArrayCreatesNewInstance() :void
    {
        $original = new MockOptions(['foo' => 'bar']);
        $created = MockOptions::create($original);
        $this->assertSame($original, $created);
    }

    /**
     * @throws ReflectionException
     */
    public function testCreateWithNullReturnsNewInstance() :void
    {
        $instance = MockOptions::create();
        $this->assertInstanceOf(MockOptions::class, $instance);
        $this->assertEquals( json_encode(['bar' => false]) , json_encode($instance->toArray(true)) );
    }

    /**
     * @throws ReflectionException
     */
    public function testCloneReturnsDeepCopy() :void
    {
        $original = new MockOptions(['foo' => 'bar']);
        $cloned = $original->clone();

        $this->assertNotSame($original, $cloned);
        $this->assertEquals($original->toArray(), $cloned->toArray());
    }

    /**
     * @throws ReflectionException
     */
    public function testFormatReplacesPlaceholders() :void
    {
        $options = $this->getConcreteOptionsInstance(['foo' => 'hello', 'bar' => true]);
        $template = 'Prefix: {{foo}}, Flag: {{bar}}, Missing: {{baz}}';
        $result = $options->format($template);
        $this->assertSame('Prefix: hello, Flag: 1, Missing: ', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testFormatWithCustomDelimiters() :void
    {
        $options = $this->getConcreteOptionsInstance(['foo' => 'abc']);
        $template = 'Value is <<foo>>';
        $result = $options->format($template, '<<', '>>');
        $this->assertSame('Value is abc', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testFormatReturnsNullForInvalidTemplate() :void
    {
        $options = $this->getConcreteOptionsInstance();
        $this->assertNull($options->format());
        $this->assertNull($options->format(''));
        $this->assertNull($options->format(Char::EMPTY));
    }

    public function testFormatArrayRecursivelyFormatsValues() :void
    {
        $options = $this->getConcreteOptionsInstance([
            'domain' => 'domain.com',
            'subdomain' => 'www'
        ]);
        $array = [
            'url' => 'https://{{subdomain}}.{{domain}}',
            'nested' => [
                'api' => 'https://api.{{domain}}/v1'
            ],
            'unchanged' => 123
        ];
        $expected = [
            'url' => 'https://www.domain.com',
            'nested' => [
                'api' => 'https://api.domain.com/v1'
            ],
            'unchanged' => 123
        ];
        $result = $options->formatArray($array);
        $this->assertSame($expected, $result);
    }

    public function testToStringReturnsDefaultString() :void
    {
        $options = new MockOptions();
        $this->assertSame('OptionsToString', (string) $options );
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionsReturnsCorrectString() :void
    {
        $options = $this->getConcreteOptionsInstance
        ([
            'foo' => 'value',
            'bar' => true,
            'baz' => ['item1', 'item2'],
        ]);

        $result = $options->getOptions(MockOption::class);
        $this->assertStringContainsString('--foo "value"', $result);
        $this->assertStringContainsString('--bar', $result);
        $this->assertStringContainsString('--baz "item1"', $result);
        $this->assertStringContainsString('--baz "item2"', $result);
    }

    public function testGetOptionsWithNullClazzReturnsEmptyString() :void
    {
        $options = $this->getConcreteOptionsInstance(['foo' => 'value']);
        $result = $options->getOptions(null);
        $this->assertSame('', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionsWithPrefix() :void
    {
        $options = $this->getConcreteOptionsInstance(['foo' => 'value', 'bar' => true]);
        $result = $options->getOptions(MockOption::class, 'PREFIX_');
        $this->assertStringContainsString('PREFIX_foo "value"', $result);
        $this->assertStringContainsString('PREFIX_bar', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionsThrowsExceptionForInvalidClass() :void
    {
        $options = $this->getConcreteOptionsInstance(['foo' => 'val']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must inherit the Option class/');
        $options->getOptions(\stdClass::class);
    }

    /**
     * @throws ReflectionException
     */
    public function testToArrayReturnsCorrectValues() :void
    {
        $opts = new TestOptions
        ([
            'host'  => 'localhost',
            'port'  => 8080,
            'flags' => ['a', 'b'],
            'debug' => true,
        ]);

        $array = $opts->toArray();

        $this->assertEquals
        ([
            'host' => 'localhost',
            'port' => 8080,
            'flags' => ['a', 'b'],
            'debug' => true,
        ] , $array );
    }

    /**
     * @throws ReflectionException
     */
    public function testToArrayWithClearRemovesNullAndEmpty() :void
    {
        $opts = new TestOptions
        ([
            'host' => 'localhost',
            'port' => null,
            'flags' => [],
            'debug' => null,
            'empty' => '',
        ]);
        $array = $opts->toArray(true);
        $this->assertSame(['host' => 'localhost'], $array);
    }

    public function testJsonSerializeProducesCorrectJson() :void
    {
        $opts = new TestOptions([
            'host' => 'localhost',
            'port' => 80,
            'flags' => [],
            'debug' => null,
            'empty' => '',
        ]);
        $json = json_encode($opts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertJson($json);
        $this->assertSame('{"host":"localhost","port":80}', $json);
    }

    public function testJsonSerializeWithEmptyValues() :void
    {
        $opts = new TestOptions
        ([
            'host' => '',
            'port' => null,
            'flags' => [],
            'debug' => null,
            'empty' => '',
        ]);
        $json = json_encode($opts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertJson($json);
        $this->assertEquals('{}', $json);
    }

    /**
     * @throws ReflectionException
     */
    public function testResolveMergesMultipleSources() :void
    {
        $default        = new TestOptions(['host' => 'default', 'port' => 80]);
        $overrideArray  = ['host' => 'override', 'debug' => true];
        $overrideObject = new TestOptions(['port' => 8080]);
        $result = TestOptions::resolve($default, $overrideArray, $overrideObject);
        $this->assertInstanceOf(TestOptions::class, $result);
        $arr = $result->toArray( true );
        $this->assertEquals(['host' => 'override', 'port' => 8080, 'debug' => true], $arr);
    }

    public function testResolveThrowsOnInvalidSource() :void
    {
        $this->expectException( InvalidArgumentException::class );
        $this->expectExceptionMessage('Invalid source type');
        MockOptions::resolve('invalid' ) ;
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionsWithCallablePrefix() :void
    {
        $options = $this->getConcreteOptionsInstance
        ([
            'foo' => 'value',
            'bar' => true,
            'baz' => ['one', 'two'],
        ]);

        $callablePrefix = function (string $property): string
        {
            return match( $property )
            {
                'foo'   => '--',
                'bar'   => '-',
                'baz'   => '/opt:',
                default => '',
            };
        };

        $result = $options->getOptions(MockOption::class, $callablePrefix );

        $this->assertStringContainsString('--foo "value"'    , $result ) ;
        $this->assertStringContainsString('-bar'             , $result ) ;
        $this->assertStringContainsString('/opt:baz "one"' , $result ) ;
        $this->assertStringContainsString('/opt:baz "two"' , $result ) ;
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionsWithCustomSeparator(): void
    {
        $options = $this->getConcreteOptionsInstance
        ([
            'foo' => 'value',
            'bar' => true,
        ]);

        $result = $options->getOptions(MockOption::class, '--', excludes: [] , separator: '=' ) ;

        $this->assertStringContainsString('--foo="value"', $result);
        $this->assertStringContainsString('--bar', $result); // Pas de separator car booléen true
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionsWithCallableSeparator(): void
    {
        $options = $this->getConcreteOptionsInstance([
            'foo' => 'value',
            'bar' => true,
            'baz' => ['one', 'two'],
        ]);

        $separatorCallable = fn(string $name): string => match($name)
        {
            'foo' => '=',
            'baz' => ':',
            default => Char::SPACE,
        };

        $result = $options->getOptions(MockOption::class, '--', excludes: [], separator: $separatorCallable);

        $this->assertStringContainsString('--foo="value"' , $result ) ; // → séparateur '='
        $this->assertStringContainsString('--bar'         , $result ) ; // → booléen → pas de séparateur
        $this->assertStringContainsString('--baz:"one"'   , $result ) ; // → séparateur ':'
        $this->assertStringContainsString('--baz:"two"'   , $result ) ;
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionsWithOrder()
    {
        $options = new MockOptions();
        $options->foo = 'value';
        $options->bar = true;
        $options->baz = ['one', 'two'];
        $options->alpha = 'a';
        $options->zeta = 'z';

        $result = $options->getOptions
        (
            MockOption::class,
            prefix: '--',
            order: ['baz', 'foo']
        );

        $this->assertGreaterThan(
            strpos($result, '--baz "one"'),
            strpos($result, '--foo "value"'),
            "Expected --baz to appear before --foo"
        );

        $this->assertStringContainsString('--bar', $result);
        $this->assertStringContainsString('--alpha "a"', $result);
        $this->assertStringContainsString('--zeta "z"', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetOptionsWithOrderAndReverse()
    {
        $options = new MockOptions();
        $options->foo = 'value';
        $options->bar = true;
        $options->baz = ['one', 'two'];
        $options->alpha = 'a';
        $options->zeta = 'z';

        $result = $options->getOptions(
            MockOption::class,
            prefix: '--',
            order: ['foo', 'baz'],
            reverseOrder: true
        );

        $this->assertLessThan(
            strpos($result, '--baz "one"'),
            strpos($result, '--foo "value"'),
            "Expected --baz to appear before --foo due to reverseOrder"
        );

        $this->assertStringContainsString('--bar', $result);
        $this->assertStringContainsString('--alpha "a"', $result);
        $this->assertStringContainsString('--zeta "z"', $result);
    }
}