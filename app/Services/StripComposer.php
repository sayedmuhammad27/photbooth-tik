<?php

namespace App\Services;

use App\Models\Template;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class StripComposer
{
    /** Decode each photo once; reuse a crop when a strip repeats the same slot size. */
    public function compose(Template $template, array $files): ImageInterface
    {
        $manager = new ImageManager(new Driver());
        $width = Template::CANVAS_WIDTH;
        $height = Template::CANVAS_HEIGHT;
        $canvas = $manager->create($width, $height)->fill($template->bg_color ?: '#131947');
        $layout = $template->normalizedSlots();
        $groups = [];
        $photos = [];

        foreach ($layout as $slot) {
            $groups[$slot['photo']][$slot['width'].'x'.$slot['height']][] = $slot;
        }

        foreach ($groups as $index => $sizes) {
            if (! isset($files[$index])) {
                continue;
            }
            $original = $this->readPhoto($manager, $files[$index]);
            foreach ($sizes as $size => $slots) {
                $photo = count($sizes) === 1 ? $original : clone $original;
                $photo->cover($slots[0]['width'], $slots[0]['height']);
                $photos[$index][$size] = $photo;
                unset($photo);
            }
            unset($original);
        }

        // Preserve template paint order, including overlapping slots.
        foreach ($layout as $slot) {
            $size = $slot['width'].'x'.$slot['height'];
            if (isset($photos[$slot['photo']][$size])) {
                $canvas->place($photos[$slot['photo']][$size], 'top-left', $slot['x'], $slot['y']);
            }
        }
        unset($photos);

        $disk = Storage::disk('public');
        if ($template->frame_asset && $disk->exists($template->frame_asset)) {
            $frame = $manager->read($disk->path($template->frame_asset));
            if ($frame->width() !== $width || $frame->height() !== $height) {
                $frame->resize($width, $height);
            }
            $canvas->place($frame, 'top-left', 0, 0);
        }

        return $canvas;
    }

    protected function readPhoto(ImageManager $manager, UploadedFile $file): ImageInterface
    {
        return $manager->read($file->getRealPath());
    }
}
