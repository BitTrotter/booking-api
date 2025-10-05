<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\FromCollection;

class DownloadMedicalRecord implements FromView
{
    protected $medical_records;
    public function __construct($medical_records)
    {
        $this->medical_records = $medical_records;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function view(): View
    {
        return view("medical_records.download_excel",[
            "medical_records" => $this->medical_records,
        ]);
    }
}
