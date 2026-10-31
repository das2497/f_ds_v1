<?php

namespace Tests\Unit;

use App\Services\Vendor\PrintfulService;
use PHPUnit\Framework\TestCase;

class PrintfulServiceTest extends TestCase
{
    public function test_it_maps_front_and_back_placements_to_printfuls_file_types(): void
    {
        $files = (new PrintfulService)->buildOrderFiles([
            'front' => ['print_file_url' => 'https://example.com/front.png'],
            'back' => ['print_file_url' => 'https://example.com/back.png'],
        ]);

        $this->assertSame([
            ['type' => 'default', 'url' => 'https://example.com/front.png'],
            ['type' => 'back', 'url' => 'https://example.com/back.png'],
        ], $files);
    }

    public function test_it_skips_placements_without_a_saved_print_file(): void
    {
        $files = (new PrintfulService)->buildOrderFiles([
            'front' => ['print_file_url' => null],
            'back' => [],
        ]);

        $this->assertSame([], $files);
    }

    public function test_it_passes_through_an_unrecognized_placement_name_as_its_own_type(): void
    {
        $files = (new PrintfulService)->buildOrderFiles([
            'sleeve' => ['print_file_url' => 'https://example.com/sleeve.png'],
        ]);

        $this->assertSame([
            ['type' => 'sleeve', 'url' => 'https://example.com/sleeve.png'],
        ], $files);
    }
}
