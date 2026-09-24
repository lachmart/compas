<?php

use App\Support\AttemptPhotos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Children's photos used to be stored on the public disk (reachable by URL
// without login) with their original EXIF data. Move each one to the private
// disk, re-encoded without metadata, and delete the public copy.
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');

        DB::table('location_attempts')
            ->whereNotNull('submitted_photo_path')
            ->orderBy('id')
            ->each(function ($attempt) use ($public) {
                $old = $attempt->submitted_photo_path;

                if (! $public->exists($old)) {
                    return;
                }

                try {
                    $new = AttemptPhotos::store($public->path($old));
                } catch (Throwable $e) {
                    // Unreadable image: keep nothing publicly reachable.
                    report($e);
                    $new = null;
                }

                DB::table('location_attempts')->where('id', $attempt->id)->update(['submitted_photo_path' => $new]);
                $public->delete($old);
            });
    }

    public function down(): void
    {
        // Irreversible on purpose: photos never go back to the public disk.
    }
};
