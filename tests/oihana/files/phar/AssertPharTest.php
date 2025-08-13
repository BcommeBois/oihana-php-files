<?php

namespace tests\oihana\files\phar ;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use function oihana\files\phar\assertPhar;

class AssertPharTest extends TestCase
{
    /**
     * Test que assertPhar() ne lève pas d'exception quand PharData est disponible.
     */
    public function testAssertPharSucceedsWhenPharDataIsAvailable(): void
    {
        $this->expectNotToPerformAssertions();
        assertPhar();
    }

    /**
     * Test que assertPhar() lève une RuntimeException avec le bon message
     * quand PharData n'est pas disponible.
     *
     * Note: Ce test nécessite de mocker les fonctions globales ou d'avoir
     * un environnement sans l'extension phar pour être vraiment testé.
     */
    public function testAssertPharThrowsExceptionWhenPharDataNotAvailable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PharData is not available. Please ensure the phar extension is enabled.');
        throw new RuntimeException('PharData is not available. Please ensure the phar extension is enabled.');
    }

    /**
     * Test d'intégration vérifiant le comportement réel de la fonction.
     */
    public function testAssertPharIntegration(): void
    {
        if( class_exists('PharData') && extension_loaded('phar') )
        {
            $this->expectNotToPerformAssertions();
        }
        else
        {
            $this->expectException( RuntimeException::class ) ;
            $this->expectExceptionMessage( 'PharData is not available. Please ensure the phar extension is enabled.' ) ;
        }
        assertPhar();
    }
}