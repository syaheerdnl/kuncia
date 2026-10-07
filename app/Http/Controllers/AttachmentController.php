<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves private uploads only to people allowed to see the parent record. */
class AttachmentController extends Controller
{
    public function show(Attachment $attachment): StreamedResponse
    {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404);

        Gate::authorize('view', $parent);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name);
    }
}
