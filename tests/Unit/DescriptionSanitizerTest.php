<?php

namespace Tests\Unit;

use App\Services\Html\DescriptionSanitizer;
use PHPUnit\Framework\TestCase;

class DescriptionSanitizerTest extends TestCase
{
    public function test_it_keeps_basic_formatting_tags(): void
    {
        $html = '<p>Hello <strong>world</strong></p><ul><li>One</li><li>Two</li></ul>';

        $this->assertSame($html, DescriptionSanitizer::sanitize($html));
    }

    public function test_it_strips_scripts_and_event_attributes(): void
    {
        $html = '<p onclick="alert(1)">Hi <script>alert(1)</script><img src=x onerror=alert(1)> there</p>';

        $this->assertSame('<p>Hi  there</p>', DescriptionSanitizer::sanitize($html));
    }

    public function test_it_unwraps_disallowed_tags_but_keeps_their_text(): void
    {
        $html = '<div><span>Keep this text</span></div>';

        $this->assertSame('Keep this text', DescriptionSanitizer::sanitize($html));
    }

    public function test_it_returns_null_for_blank_input(): void
    {
        $this->assertNull(DescriptionSanitizer::sanitize(null));
        $this->assertNull(DescriptionSanitizer::sanitize(''));
        $this->assertNull(DescriptionSanitizer::sanitize('   '));
    }

    public function test_it_returns_null_when_everything_is_stripped(): void
    {
        $this->assertNull(DescriptionSanitizer::sanitize('<script>alert(1)</script>'));
    }

    public function test_plain_text_passes_through_unchanged(): void
    {
        $text = "Product information:\nColor: white, pink, blue, green";

        $this->assertSame($text, DescriptionSanitizer::sanitize($text));
    }
}
