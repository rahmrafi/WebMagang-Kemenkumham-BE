<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Submission;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documentService) {}

    /**
     * GET /api/admin/submissions/{submission}/generate-template
     * Copy template DOCX, isi semua placeholder, lalu download.
     */
    public function generateTemplate(Submission $submission): BinaryFileResponse
    {
        $result = $this->documentService->generateDocx($submission);

        return response()
            ->download($result['tempPath'], $result['fileName'], [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ])
            ->deleteFileAfterSend(true);
    }
}
