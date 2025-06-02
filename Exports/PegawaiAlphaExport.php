<?php

namespace Modules\Kedisiplinan\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PegawaiAlphaExport implements FromArray, WithHeadings
{
    protected $pegawai;
    protected $alphaPerBulan;
    protected $year;

    public function __construct($pegawai, $alphaPerBulan, $year)
    {
        $this->pegawai = $pegawai;
        $this->alphaPerBulan = $alphaPerBulan;
        $this->year = $year;
    }

    public function array(): array
    {
        $data = [];
        $isFirstRow = true;

        foreach ($this->alphaPerBulan as $bulan => $tanggalList) {
            foreach ($tanggalList as $tanggal) {
                $data[] = [
                    'Nama'    => $isFirstRow ? $this->pegawai->nama : '',
                    'NIP'     => $isFirstRow ? $this->pegawai->nip : '',
                    'Tanggal' => Carbon::parse($tanggal)->format('Y-m-d'),
                    'Hari'    => Carbon::parse($tanggal)->translatedFormat('l'),
                    'Bulan'   => $bulan,
                    'Tahun'   => $this->year,
                ];
                $isFirstRow = false;
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return ['Nama', 'NIP', 'Tanggal', 'Hari', 'Bulan', 'Tahun'];
    }
}
