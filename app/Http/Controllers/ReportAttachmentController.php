<?php

namespace App\Http\Controllers;

use App\Models\ReportAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportAttachmentController extends Controller
{
    public function preview(ReportAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->report);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name);
    }

    public function download(ReportAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->report);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
