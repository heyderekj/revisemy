<?php

namespace App\Console\Commands;

use App\Models\Screenshot;
use App\Services\ScreenshotStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillScreenshotThumbnails extends Command
{
    protected $signature = 'revisemy:backfill-thumbnails';

    protected $description = 'Generate rail thumbnails for screenshots stored before thumbnails existed';

    public function handle(ScreenshotStorage $storage): int
    {
        $made = 0;
        $skipped = 0;

        Screenshot::query()->whereNull('thumb_path')->each(function (Screenshot $shot) use ($storage, &$made, &$skipped) {
            try {
                $binary = Storage::disk($shot->disk)->get($shot->path);
                $thumb = $binary === null ? null : $storage->makeThumbnail($binary);
            } catch (\Throwable) {
                $thumb = null;
            }

            if ($thumb === null) {
                $skipped++;

                return;
            }

            $thumbPath = preg_replace('/\.[a-z]+$/', '', $shot->path).'.thumb.jpg';
            Storage::disk($shot->disk)->put($thumbPath, $thumb);
            $shot->update(['thumb_path' => $thumbPath]);
            $made++;
        });

        $this->info("Thumbnails generated: {$made}, skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
