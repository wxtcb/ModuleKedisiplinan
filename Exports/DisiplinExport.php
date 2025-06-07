<?php

namespace Modules\Kedisiplinan\Exports;

use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class DisiplinExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $pegawai;
    protected $tanggalKurangJam;
    protected $totalKurangJam;
    protected $absensiTidakLengkap;
    protected $bulanTerburuk;

    public function __construct($pegawai, $tanggalKurangJam, $totalKurangJam, $absensiTidakLengkap, $bulanTerburuk)
    {
        $this->pegawai = $pegawai;
        $this->tanggalKurangJam = $tanggalKurangJam;
        $this->totalKurangJam = $totalKurangJam;
        $this->absensiTidakLengkap = $absensiTidakLengkap;
        $this->bulanTerburuk = $bulanTerburuk;
    }

    public function collection()
    {
        return collect($this->tanggalKurangJam);
    }

    public function headings(): array
    {
        return [
            ['REKAPITULASI DISIPLIN JAM KERJA PEGAWAI'],
            [],
            [
                'NIP',
                'Nama',
                'Jabatan',
                'Total Hari Kurang Jam',
                'Total Jam Kurang',
                'Absen Tidak Lengkap',
                'Bulan Terburuk'
            ],
            [
                $this->pegawai->nip,
                $this->pegawai->nama,
                in_array('dosen', $this->getRoles()) ? 'Dosen' : 'Staff',
                count($this->tanggalKurangJam),
                $this->totalKurangJam . ' jam',
                $this->absensiTidakLengkap . ' hari',
                $this->bulanTerburuk
            ],
            [],
            [
                'No',
                'Tanggal',
                'Jam Masuk',
                'Jam Pulang',
                'Jam Kerja',
                'Kewajiban Jam',
                'Jam Kurang',
                'Keterangan'
            ]
        ];
    }

    public function map($item): array
    {
        return [
            '', // Placeholder for numbering (will be handled in styles)
            Carbon::parse($item['tanggal'])->format('d-m-Y'),
            $item['jam_masuk'],
            $item['jam_pulang'],
            number_format($item['jam_kerja'], 2) . ' jam',
            $item['kewajiban'] . ' jam',
            number_format($item['kurang'], 2) . ' jam',
            $item['keterangan']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Merge title row
        $sheet->mergeCells('A1:H1');
        
        // Set styles
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
            ],
            'alignment' => [
                'horizontal' => 'center',
            ],
        ]);

        // Header styles
        $sheet->getStyle('A3:H3')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['argb' => 'FFD9D9D9'],
            ],
        ]);

        $sheet->getStyle('A6:H6')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['argb' => 'FFD9D9D9'],
            ],
        ]);

        // Add numbering to the data rows
        $row = 7;
        foreach ($this->tanggalKurangJam as $index => $item) {
            $sheet->setCellValue('A' . $row, $index + 1);
            $row++;
        }

        // Set borders for data
        $sheet->getStyle('A6:H' . (6 + count($this->tanggalKurangJam)))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Auto filter
        $sheet->setAutoFilter('A6:H' . (6 + count($this->tanggalKurangJam)));
    }

    public function title(): string
    {
        return 'Disiplin ' . substr($this->pegawai->nama, 0, 20);
    }

    private function getRoles()
    {
        return DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->join('users', 'model_has_roles.model_id', '=', 'users.id')
            ->where('users.username', $this->pegawai->username)
            ->pluck('roles.name')
            ->toArray();
    }
}