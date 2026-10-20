<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceReportExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Employee ID', 'Employee Name', 'Date', 'Time In', 'Time Out',
            'Working Hours', 'Status', 'Regularized',
        ];
    }

    public function map($attendance): array
    {
        return [
            $attendance->user->employee_id ?? '',
            $attendance->user->name ?? '',
            optional($attendance->date)->format('Y-m-d'),
            $attendance->time_in?->format('Y-m-d H:i'),
            $attendance->time_out?->format('Y-m-d H:i'),
            $attendance->working_hours,
            ucfirst(str_replace('_', ' ', $attendance->status)),
            $attendance->is_regularized ? 'Yes' : 'No',
        ];
    }
}
