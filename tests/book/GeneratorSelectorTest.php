<?php

namespace App\Tests;

use App\Generator\ConvertGenerator;
use App\Generator\Epub2Generator;
use App\Generator\Epub3Generator;
use App\GeneratorSelector;
use App\Exception\WSExportInvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers GeneratorSelector
 */
class GeneratorSelectorTest extends TestCase {

	public function testGetUnknownGeneratorRaisesException() {
		$this->expectException( WSExportInvalidArgumentException::class );
		$this->expectExceptionMessage( "The file format 'unknown' is unknown." );
		GeneratorSelector::select( "unknown" );
	}

	public function testGetGeneratorEpub2() {
		$generator = GeneratorSelector::select( 'epub-2' );
		$this->assertInstanceOf( Epub2Generator::class, $generator );
	}

	public function testGetGeneratorEpub3() {
		$generator = GeneratorSelector::select( 'epub-3' );
		$this->assertInstanceOf( Epub3Generator::class, $generator );
	}

	public function testGetGeneratorMobi() {
		$generator = GeneratorSelector::select( 'mobi' );
		$this->assertInstanceOf( ConvertGenerator::class, $generator );
		$this->assertEquals( 'application/x-mobipocket-ebook', $generator->getMimeType() );
	}

	public function testGetGeneratorTxt() {
		$generator = GeneratorSelector::select( 'txt' );
		$this->assertInstanceOf( ConvertGenerator::class, $generator );
		$this->assertEquals( 'text/plain', $generator->getMimeType() );
	}

	public function testGetGeneratorRtf() {
		$generator = GeneratorSelector::select( 'rtf' );
		$this->assertInstanceOf( ConvertGenerator::class, $generator );
		$this->assertEquals( 'application/rtf', $generator->getMimeType() );
	}

	public function testGetGeneratorOdt() {
		$generator = GeneratorSelector::select( 'odt' );
		$this->assertInstanceOf( ConvertGenerator::class, $generator );
		$this->assertEquals( 'application/rtf', $generator->getMimeType() );
	}

	public function testGetGeneratorPdfA4() {
		$generator = GeneratorSelector::select( 'pdf-a4' );
		$this->assertInstanceOf( ConvertGenerator::class, $generator );
		$this->assertEquals( 'application/pdf', $generator->getMimeType() );
	}

	public function testGetGeneratorPdfA5() {
		$generator = GeneratorSelector::select( 'pdf-a5' );
		$this->assertInstanceOf( ConvertGenerator::class, $generator );
		$this->assertEquals( 'application/pdf', $generator->getMimeType() );
	}

	public function testGetGeneratorPdfLetter() {
		$generator = GeneratorSelector::select( 'pdf-letter' );
		$this->assertInstanceOf( ConvertGenerator::class, $generator );
		$this->assertEquals( 'application/pdf', $generator->getMimeType() );
	}
}
