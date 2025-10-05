<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\FromCollection;

class DownloadVaccination implements FromView
{
    protected $vaccinations;
    public function __construct($records_vaccinations)
    {
        $this->vaccinations = $records_vaccinations;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function view(): View
    {
        return view("vaccinations.download_excel",[
            "vaccinations" => $this->vaccinations,
        ]);
    }
}
