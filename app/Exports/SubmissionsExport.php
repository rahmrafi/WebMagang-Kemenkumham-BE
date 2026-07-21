<?php

namespace App\Exports;

use App\Models\Submission;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubmissionsExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $submissionId;

    public function __construct($submissionId = null)
    {
        $this->submissionId = $submissionId;
    }

    public function view(): View
    {
        $query = Submission::query()->with('period');
        if ($this->submissionId) {
            $query->where('id', $this->submissionId);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return view('exports.submissions', [
            'submissions' => $query->get()
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        // Add border to all cells
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        
        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ]);
    }
}
