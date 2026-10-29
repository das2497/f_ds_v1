<?php

namespace Tests\Unit;

use App\Services\Html\ColorSwatch;
use PHPUnit\Framework\TestCase;

class ColorSwatchTest extends TestCase
{
    public function test_it_resolves_simple_css_color_names(): void
    {
        $this->assertSame('red', ColorSwatch::resolve('Red'));
        $this->assertSame('navy', ColorSwatch::resolve('navy'));
        $this->assertSame('black', ColorSwatch::resolve('BLACK'));
    }

    public function test_it_resolves_compound_keywords_written_with_a_space(): void
    {
        $this->assertSame('lightblue', ColorSwatch::resolve('Light Blue'));
        $this->assertSame('hotpink', ColorSwatch::resolve('Hot Pink'));
        $this->assertSame('darkgreen', ColorSwatch::resolve('Dark-Green'));
    }

    public function test_it_returns_null_for_non_css_vendor_labels(): void
    {
        $this->assertNull(ColorSwatch::resolve('Glossy'));
        $this->assertNull(ColorSwatch::resolve('Matte'));
        $this->assertNull(ColorSwatch::resolve('Rose Gold'));
        $this->assertNull(ColorSwatch::resolve('Space Gray'));
    }

    public function test_it_returns_null_for_blank_input(): void
    {
        $this->assertNull(ColorSwatch::resolve(null));
        $this->assertNull(ColorSwatch::resolve(''));
        $this->assertNull(ColorSwatch::resolve('   '));
    }
}
