<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\FromCollection;

class DownloadSurgerie implements FromView
{
    protected $surgeries;
    public function __construct($records_surgeries)
    {
        $this->surgeries = $records_surgeries;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function view(): View
    {
        return view("surgeries.download_excel",[
            "surgeries" => $this->surgeries,
        ]);
    }
}
