<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Services\StripComposer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Tests\TestCase;

class StripComposerTest extends TestCase
{
    public function test_duplicate_crops_decode_once_and_preserve_pixels_and_paint_order(): void
    {
        Storage::fake('public');
        $manager = new ImageManager(new Driver());
        $template = new Template([
            'slot_count' => 2, 'bg_color' => '#131947',
            'layout_config' => [
                ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 300, 'photo' => 0],
                ['x' => 50, 'y' => 50, 'width' => 400, 'height' => 300, 'photo' => 1],
                ['x' => 100, 'y' => 100, 'width' => 400, 'height' => 300, 'photo' => 0],
                ['x' => 500, 'y' => 500, 'width' => 200, 'height' => 500, 'photo' => 0],
            ],
        ]);
        $files = [];
        foreach (['#cc2211', '#1155cc'] as $color) {
            $image = $manager->create(640, 480)->fill($color);
            $image->place($manager->create(200, 200)->fill('#33cc55'), 'top-left', 40, 70);
            $files[] = UploadedFile::fake()->createWithContent('photo.jpg', (string) $image->toJpeg(92));
        }
        $reference = $manager->create(1200, 1800)->fill($template->bg_color);
        foreach ($template->normalizedSlots() as $slot) {
            $photo = $manager->read($files[$slot['photo']]->getRealPath())->cover($slot['width'], $slot['height']);
            $reference->place($photo, 'top-left', $slot['x'], $slot['y']);
        }

        $composer = new class extends StripComposer {
            public int $reads = 0;
            protected function readPhoto(ImageManager $manager, UploadedFile $file): ImageInterface
            {
                $this->reads++;
                return parent::readPhoto($manager, $file);
            }
        };
        $actual = $composer->compose($template, $files);
        $this->assertSame(2, $composer->reads);
        $this->assertSame(1200, $actual->width());
        $this->assertSame(1800, $actual->height());
        $this->assertSame((string) $reference->toPng(), (string) $actual->toPng());
    }
}
