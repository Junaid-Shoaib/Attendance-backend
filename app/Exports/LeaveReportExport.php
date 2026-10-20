<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeaveReportExport implements FromCollection, WithHeadings, WithMapping
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
            'Employee ID', 'Employee Name', 'Leave Type', 'Start Date', 'End Date',
            'Total Days', 'Status', 'Reason',
        ];
    }

    public function map($leave): array
    {
        return [
            $leave->user->employee_id ?? '',
            $leave->user->name ?? '',
            $leave->leaveType->name ?? '',
            optional($leave->start_date)->format('Y-m-d'),
            optional($leave->end_date)->format('Y-m-d'),
            $leave->total_days,
            ucfirst(str_replace('_', ' ', $leave->status)),
            $leave->reason,
        ];
    }
}
