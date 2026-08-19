<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use App\Models\MessageExportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageExportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeUtility(Utility::MessageExport);

        return view('utilities.message-export');
    }

    public function history(Request $request)
    {
        $this->authorizeUtility(Utility::MessageExport);

        return view('utilities.message-export-history');
    }

    public function download(Request $request, MessageExportLog $log): StreamedResponse
    {
        $this->authorizeUtility(Utility::MessageExport);

        // Verify team ownership
        if ((int) $log->team_id !== (int) $request->user()->currentTeam->id) {
            abort(403);
        }

        if (! $log->file_path || ! Storage::exists($log->file_path)) {
            abort(404, 'Export file is no longer available.');
        }

        $encryptedContent = Storage::get($log->file_path);
        $csvContent = decrypt($encryptedContent);

        $filename = 'message-export-'.$log->created_at->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
