<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$manager = new Intervention\Image\ImageManager(new Intervention\Image\Drivers\Gd\Driver());
$data = json_decode(file_get_contents(__DIR__.'/../database/seeders/data/templates.json'), true)[0];
$template = new App\Models\Template($data);
$template->frame_asset = null;
$files = [];
foreach (['#aa3311', '#33aa11', '#3311aa', '#aa11aa'] as $color) {
    $image = $manager->create(1600, 1015)->fill($color);
    $files[] = Illuminate\Http\UploadedFile::fake()->createWithContent('photo.jpg', (string) $image->toJpeg(92));
}
$composer = new App\Services\StripComposer();
$times = ['before' => [], 'after' => []];
for ($i = 0; $i < 9; $i++) {
    foreach (array_keys($times) as $mode) {
        $start = hrtime(true);
        if ($mode === 'before') {
            $canvas = $manager->create(1200, 1800)->fill($template->bg_color);
            foreach ($template->normalizedSlots() as $slot) {
                $photo = $manager->read($files[$slot['photo']]->getRealPath())->cover($slot['width'], $slot['height']);
                $canvas->place($photo, 'top-left', $slot['x'], $slot['y']);
            }
        } else {
            $canvas = $composer->compose($template, $files);
        }
        $canvas->toJpeg(92);
        $times[$mode][] = (hrtime(true) - $start) / 1e6;
        unset($canvas, $photo);
    }
}
$medians = [];
foreach ($times as $mode => $samples) { sort($samples); $medians[$mode] = round($samples[4], 1); }
echo json_encode([
    'scenario' => '4 photos 1600x1015, 8 slots, 1200x1800 output JPEG 92; median of 9 runs',
    'milliseconds' => $medians,
    'reduction_percent' => round((1 - $medians['after'] / $medians['before']) * 100, 1),
], JSON_PRETTY_PRINT).PHP_EOL;
