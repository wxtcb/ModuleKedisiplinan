<?php

namespace Modules\Kedisiplinan\Http\Controllers;

use App\Models\Core\User;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Cuti\Entities\Cuti;
use Modules\Kedisiplinan\Entities\Alpha;
use Modules\Kedisiplinan\Entities\Sanksi;
use Modules\Kedisiplinan\Exports\PegawaiAlphaExport;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\Setting\Entities\Jam;
use Modules\Setting\Entities\Libur;
use Modules\SuratIjin\Entities\LupaAbsen;
use Modules\SuratIjin\Entities\Terlambat;
use Modules\SuratTugas\Entities\SuratTugas;

class AlphaController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {

        $user = auth()->user();
        $year = $request->input('year', now()->year);
        $bulanSekarang = now()->month;
        $tanggalHariIni = now()->toDateString();
        $roles = $user->getRoleNames()->toArray();

        $pegawaiList = Pegawai::query();

        if (!in_array('admin', $roles) && !in_array('super', $roles)) {
            if (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                $pegawaiList->where('username', $user->username);
            } else {
                $pegawaiList->whereNull('id');
            }
        }

        $pegawaiList = $pegawaiList->select('id', 'nama', 'nip', 'username')->get();
        $pegawaiIDs = $pegawaiList->pluck('id')->toArray();

        $kehadiran = Alpha::query()
            ->whereYear('checktime', $year)
            ->when(!in_array('admin', $roles), function ($query) use ($user, $roles) {
                if (in_array('pegawai', $roles) || in_array('dosen', $roles)) {
                    return $query->where('user_id', $user->id);
                }
                return $query->whereNull('user_id');
            })
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id . '|' . Carbon::parse($item->checktime)->format('Y-m-d');
            });

        $tanggalLibur = Libur::whereYear('tanggal', $year)->pluck('tanggal')->map(function ($tanggal) {
            return \Carbon\Carbon::parse($tanggal)->format('Y-m-d');
        })->toArray();

        $cuti = Cuti::where('status', 'Selesai')
            ->whereIn('pegawai_id', $pegawaiIDs)
            ->whereYear('tanggal_mulai', '<=', $year)
            ->get();

        $cutiByPegawai = [];
        foreach ($cuti as $item) {
            $start = \Carbon\Carbon::parse($item->tanggal_mulai);
            $end = \Carbon\Carbon::parse($item->tanggal_selesai);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($date->year == $year) {
                    $cutiByPegawai[$item->pegawai_id][] = $date->format('Y-m-d');
                }
            }
        }

        // DL (Dinas Luar)
        $suratTugas = SuratTugas::with(['detail', 'anggota'])
            ->whereHas('detail', function ($q) use ($year) {
                $q->whereYear('tanggal_mulai', '<=', $year)->orWhereYear('tanggal_selesai', '<=', $year);
            })->get();

        $dinasLuarByPegawai = [];
        foreach ($suratTugas as $surat) {
            if (!$surat->detail) continue;
            $start = \Carbon\Carbon::parse($surat->detail->tanggal_mulai);
            $end = \Carbon\Carbon::parse($surat->detail->tanggal_selesai);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $tanggal = $date->format('Y-m-d');
                if ($date->year != $year) continue;

                $penanggungJawabID = $surat->detail->pegawai_id;
                $dinasLuarByPegawai[$penanggungJawabID][] = $tanggal;

                foreach ($surat->anggota as $anggota) {
                    $dinasLuarByPegawai[$anggota->pegawai_id][] = $tanggal;
                }
            }
        }

        $hariKerja = collect();
        $start = \Carbon\Carbon::create($year, 1, 1);
        $end = \Carbon\Carbon::create($year, 12, 31);

        while ($start <= $end) {
            $tanggal = $start->format('Y-m-d');
            if (!$start->isWeekend() && !in_array($tanggal, $tanggalLibur)) {
                $hariKerja->push($tanggal);
            }
            $start->addDay();
        }

        $rekapData = $pegawaiList->map(function ($pegawai) use ($kehadiran, $hariKerja, $cutiByPegawai, $dinasLuarByPegawai, $bulanSekarang, $tanggalHariIni) {
            // Inisialisasi array untuk menyimpan jumlah TM per bulan
            $tmPerBulan = array_fill(1, 12, 0);

            foreach ($hariKerja as $tanggal) {
                $bulan = \Carbon\Carbon::parse($tanggal)->month;

                // Lewati hari di masa depan untuk bulan yang sedang berjalan
                if ($bulan === $bulanSekarang && $tanggal > $tanggalHariIni) {
                    continue;
                }

                // Lewati semua hari jika bulan lebih besar dari bulan sekarang (belum dilalui)
                if ($bulan > $bulanSekarang) {
                    continue;
                }

                // Lewati jika tanggal termasuk dalam dinas luar atau cuti
                if (
                    in_array($tanggal, $dinasLuarByPegawai[$pegawai->id] ?? []) ||
                    in_array($tanggal, $cutiByPegawai[$pegawai->id] ?? [])
                ) {
                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;

                // Cek tidak ada kehadiran sama sekali
                if (!$kehadiran->has($key)) {
                    // Tidak masuk dan tidak ada surat izin
                    $izinAda = Terlambat::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->exists()
                        || LupaAbsen::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->exists();

                    if (!$izinAda) {
                        $tmPerBulan[$bulan]++;
                    }
                }
            }

            $jumlahSanksi = Sanksi::where('pegawai_id', $pegawai->id)->count();
            return [
                'id' => $pegawai->id,
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'tm_per_bulan' => $tmPerBulan,
                'jumlah_sanksi' => $jumlahSanksi,
            ];
        })->toArray();

        return view('kedisiplinan::alpha.index', compact('rekapData', 'year'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create($id)
    {
        $pegawai = Pegawai::findOrFail($id);
        return view('kedisiplinan::alpha.create', compact('pegawai'));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|integer|exists:pegawais,id',
            'pelanggaran' => 'required|string',
            'sanksi' => 'required|string',
            'rekomendasi_sanksi' => 'required|string',
            'alasan' => 'required|string',
            'rentang_sanksi' => 'required',
            'tanggal_pemeriksaan' => 'required|date',
            'BAP' => 'required|file|mimes:pdf,doc,docx|max:2048',
        ]);

        // explode tanggal sanksi
        $tanggal = $request->input('rentang_sanksi');
        $tanggalRange = explode(' to ', $tanggal);
        if (count($tanggalRange) == 2) {
            $awal_sanksi = $tanggalRange[0];
            $akhir_sanksi = $tanggalRange[1];
        }

        // proses simpan dokumen
        if ($request->hasFile('BAP')) {
            $file = $request->file('BAP');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/uploads/BAP', $fileName);
            $BAPpath = 'uploads/BAP/' . $fileName;
        } else {
            $BAPpath = null;
        }

        // save
        Sanksi::create([
            'pegawai_id' => $request->pegawai_id,
            'pelanggaran' => $request->pelanggaran,
            'sanksi' => $request->sanksi,
            'rekomendasi_sanksi' => $request->rekomendasi_sanksi,
            'alasan' => $request->alasan,
            'tanggal_mulai' => $awal_sanksi,
            'tanggal_selesai' => $akhir_sanksi,
            'tanggal_pemeriksaan' => $request->tanggal_pemeriksaan,
            'BAP' => $BAPpath,
        ]);

        return redirect()->route('alpha.index')->with('success', 'Berhasil menyimpan sanksi');
    }

    public function hitungTidakHadir(Request $request)
    {
        $request->validate([
            'pegawai_id' => 'required|integer|exists:pegawais,id',
            'bulan_range' => 'required|string',
        ]);

        $pegawai = Pegawai::findOrFail($request->pegawai_id);
        $user = User::where('username', $pegawai->username)->firstOrFail();

        $range = explode(' to ', $request->bulan_range);
        if (count($range) === 2) {
            $start = \Carbon\Carbon::parse($range[0])->startOfMonth();
            $end = \Carbon\Carbon::parse($range[1])->endOfMonth();
        } else {
            $start = $end = \Carbon\Carbon::parse($range[0])->startOfMonth();
        }

        $today = now()->toDateString();
        $bulanSekarang = now()->month;

        // Hari libur nasional
        $tanggalLibur = Libur::whereBetween('tanggal', [$start, $end])
            ->pluck('tanggal')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // Hari kerja (senin–jumat), tanpa libur & hari ke depan
        $hariKerja = collect();
        $periode = clone $start;

        while ($periode <= $end) {
            $tanggal = $periode->format('Y-m-d');
            $bulan = $periode->month;

            if (
                !$periode->isWeekend() &&
                !in_array($tanggal, $tanggalLibur) &&
                ($bulan < $bulanSekarang || ($bulan == $bulanSekarang && $tanggal <= $today))
            ) {
                $hariKerja->push($tanggal);
            }

            $periode->addDay();
        }

        // Kehadiran
        $hadir = Alpha::where('user_id', $pegawai->id)
            ->whereBetween('checktime', [$start, $end])
            ->get()
            ->pluck('checktime')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->toArray();

        // Izin Terlambat & LupaAbsen per tanggal
        $izin = [];

        $terlambat = Terlambat::where('pegawai_id', $pegawai->id)
            ->where('status', 'Disetujui')
            ->whereBetween('tanggal', [$start, $end])
            ->pluck('tanggal')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $lupaAbsen = LupaAbsen::where('pegawai_id', $pegawai->id)
            ->where('status', 'Disetujui')
            ->whereBetween('tanggal', [$start, $end])
            ->pluck('tanggal')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $izin = array_unique(array_merge($terlambat, $lupaAbsen));

        // Cuti
        $cuti = Cuti::where('pegawai_id', $pegawai->id)
            ->where('status', 'Selesai')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('tanggal_mulai', [$start, $end])
                    ->orWhereBetween('tanggal_selesai', [$start, $end]);
            })
            ->get();

        $cutiTanggal = [];
        foreach ($cuti as $c) {
            $mulai = \Carbon\Carbon::parse($c->tanggal_mulai);
            $selesai = \Carbon\Carbon::parse($c->tanggal_selesai);
            while ($mulai <= $selesai) {
                $cutiTanggal[] = $mulai->format('Y-m-d');
                $mulai->addDay();
            }
        }

        // Dinas Luar (DL)
        $suratTugas = SuratTugas::with('detail', 'anggota')
            ->whereHas('detail', fn($q) => $q->whereBetween('tanggal_mulai', [$start, $end])
                ->orWhereBetween('tanggal_selesai', [$start, $end]))
            ->get();

        $dl = [];
        foreach ($suratTugas as $s) {
            if (!$s->detail) continue;

            $mulai = \Carbon\Carbon::parse($s->detail->tanggal_mulai);
            $selesai = \Carbon\Carbon::parse($s->detail->tanggal_selesai);

            while ($mulai <= $selesai) {
                $tgl = $mulai->format('Y-m-d');

                if ($s->detail->pegawai_id == $pegawai->id || $s->anggota->contains('pegawai_id', $pegawai->id)) {
                    $dl[] = $tgl;
                }

                $mulai->addDay();
            }
        }

        // Gabungkan semua izin
        $izinAll = array_unique(array_merge($izin, $cutiTanggal, $dl));

        // Hitung tidak hadir
        $tidakHadir = $hariKerja->filter(function ($tgl) use ($hadir, $izinAll) {
            return !in_array($tgl, $hadir) && !in_array($tgl, $izinAll);
        })->count();

        return response()->json(['jumlah' => $tidakHadir]);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */

    public function show($id)
    {
        $pegawai = Pegawai::findOrFail($id);
        $year = request('year', now()->year); // Ambil tahun dari request

        // Ambil libur nasional
        $tanggalLibur = Libur::whereYear('tanggal', $year)->pluck('tanggal')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))->toArray();

        // Hitung hari kerja efektif
        $start = Carbon::createFromDate($year, 1, 1);
        $end = Carbon::createFromDate($year, 12, 31);
        $hariKerja = collect([]);

        while ($start->lte($end)) {
            $tanggal = $start->format('Y-m-d');
            if (!$start->isWeekend() && !in_array($tanggal, $tanggalLibur)) {
                $hariKerja->push($tanggal);
            }
            $start->addDay();
        }

        // Ambil absensi sesuai tahun
        $absensi = Alpha::whereYear('checktime', $year)
            ->where('user_id', $pegawai->id)
            ->get()
            ->map(fn($a) => Carbon::parse($a->checktime)->format('Y-m-d'));

        // Ambil cuti sesuai tahun
        $cuti = Cuti::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal_mulai', '<=', $year)
            ->whereYear('tanggal_selesai', '>=', $year)
            ->get();

        $tanggalCuti = collect([]);
        foreach ($cuti as $item) {
            $start = Carbon::parse($item->tanggal_mulai);
            $end = Carbon::parse($item->tanggal_selesai);
            while ($start->lte($end)) {
                $tanggal = $start->format('Y-m-d');
                if ($start->year == $year) {
                    $tanggalCuti->push($tanggal);
                }
                $start->addDay();
            }
        }

        // Ambil lupa absen sesuai tahun
        $lupaAbsen = LupaAbsen::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal', $year)
            ->where('status', 'Disetujui')
            ->pluck('tanggal');

        // Ambil terlambat sesuai tahun
        $terlambat = Terlambat::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal', $year)
            ->where('status', 'Disetujui')
            ->pluck('tanggal');

        // Ambil surat tugas / dinas luar sesuai tahun
        $suratTugas = SuratTugas::whereHas('anggota', function ($q) use ($pegawai) {
            $q->where('pegawai_id', $pegawai->id);
        })
            ->orWhereHas('detail', function ($q) use ($pegawai) {
                $q->where('pegawai_id', $pegawai->id);
            })
            ->whereHas('detail', function ($q) use ($year) {
                $q->whereYear('tanggal_mulai', '<=', $year)
                    ->whereYear('tanggal_selesai', '>=', $year);
            })
            ->with(['detail'])
            ->get();

        // Ambil tanggal dinas sesuai tahun
        $tanggalDinas = collect([]);
        foreach ($suratTugas as $st) {
            if (!$st->detail) continue;

            $start = Carbon::parse($st->detail->tanggal_mulai);
            $end = Carbon::parse($st->detail->tanggal_selesai);
            $current = clone $start;

            while ($current->lte($end)) {
                if ($current->year == $year) {
                    $tanggal = $current->format('Y-m-d');
                    $tanggalDinas->push($tanggal);
                }
                $current->addDay();
            }
        }

        // Gabung semua tanggal izin
        $tanggalIzin = $tanggalCuti
            ->merge($lupaAbsen)
            ->merge($terlambat)
            ->merge($tanggalDinas)
            ->unique()
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        // Cari alpha (hari kerja tanpa absen/izin & ≤ hari ini)
        $tanggalHariIni = now()->format('Y-m-d');
        $tahunSekarang = now()->year;

        $alphaDates = $hariKerja->filter(function ($tanggal) use ($absensi, $tanggalIzin, $year, $tahunSekarang, $tanggalHariIni) {
            $tanggalParse = Carbon::parse($tanggal);
            $bulan = $tanggalParse->month;
            $tahunAlpha = $tanggalParse->year;

            // Jika tahun yang dipilih == tahun sekarang, maka batasi sampai hari ini
            if ($year == $tahunSekarang) {
                $bulanSekarang = now()->month;
                $tanggalAlphaStr = $tanggalParse->format('Y-m-d');

                if ($bulan > $bulanSekarang || ($bulan == $bulanSekarang && $tanggalAlphaStr > $tanggalHariIni)) {
                    return false;
                }
            }

            // Cek apakah pegawai hadir atau izin
            return !$absensi->contains($tanggal) && !in_array($tanggal, $tanggalIzin);
        });

        // Kelompokkan per bulan
        $alphaPerBulan = $alphaDates->groupBy(function ($tanggal) {
            return Carbon::parse($tanggal)->locale('id')->translatedFormat('F Y'); // "Januari 2025"
        });

        return view('kedisiplinan::alpha.show', compact('pegawai', 'alphaPerBulan', 'year'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('kedisiplinan::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

    public function exportExcel($id)
    {
        $year = request('year'); // Ambil dari query string
        $pegawai = Pegawai::findOrFail($id);

        // Gunakan kembali logic show() untuk ambil $alphaPerBulan
        [$alphaPerBulan] = $this->getAlphaData($pegawai, $year);

        $fileName = "Alpha_Pegawai_{$pegawai->nip}_{$pegawai->nama}_{$year}.xlsx";

        return Excel::download(new PegawaiAlphaExport($pegawai, $alphaPerBulan, $year), $fileName);
    }

    private function getAlphaData($pegawai, $year)
    {
        $tanggalLibur = Libur::whereYear('tanggal', $year)->pluck('tanggal')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))->toArray();

        $start = Carbon::createFromDate($year, 1, 1);
        $end = Carbon::createFromDate($year, 12, 31);
        $hariKerja = collect();

        while ($start->lte($end)) {
            $tanggal = $start->format('Y-m-d');
            if (!$start->isWeekend() && !in_array($tanggal, $tanggalLibur)) {
                $hariKerja->push($tanggal);
            }
            $start->addDay();
        }

        $absensi = Alpha::whereYear('checktime', $year)
            ->where('user_id', $pegawai->id)
            ->get()
            ->map(fn($a) => Carbon::parse($a->checktime)->format('Y-m-d'));

        $cuti = Cuti::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal_mulai', '<=', $year)
            ->whereYear('tanggal_selesai', '>=', $year)
            ->get();

        $tanggalCuti = collect();
        foreach ($cuti as $item) {
            $start = Carbon::parse($item->tanggal_mulai);
            $end = Carbon::parse($item->tanggal_selesai);
            while ($start->lte($end)) {
                if ($start->year == $year) {
                    $tanggalCuti->push($start->format('Y-m-d'));
                }
                $start->addDay();
            }
        }

        $lupaAbsen = LupaAbsen::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal', $year)
            ->where('status', 'Disetujui')
            ->pluck('tanggal');

        $terlambat = Terlambat::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal', $year)
            ->where('status', 'Disetujui')
            ->pluck('tanggal');

        $suratTugas = SuratTugas::whereHas('anggota', function ($q) use ($pegawai) {
            $q->where('pegawai_id', $pegawai->id);
        })
            ->orWhereHas('detail', function ($q) use ($pegawai) {
                $q->where('pegawai_id', $pegawai->id);
            })
            ->whereHas('detail', function ($q) use ($year) {
                $q->whereYear('tanggal_mulai', '<=', $year)
                    ->whereYear('tanggal_selesai', '>=', $year);
            })
            ->with(['detail'])
            ->get();

        $tanggalDinas = collect();
        foreach ($suratTugas as $st) {
            if (!$st->detail) continue;

            $start = Carbon::parse($st->detail->tanggal_mulai);
            $end = Carbon::parse($st->detail->tanggal_selesai);

            while ($start->lte($end)) {
                if ($start->year == $year) {
                    $tanggalDinas->push($start->format('Y-m-d'));
                }
                $start->addDay();
            }
        }

        $tanggalIzin = $tanggalCuti
            ->merge($lupaAbsen)
            ->merge($terlambat)
            ->merge($tanggalDinas)
            ->unique()
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $tanggalHariIni = now()->format('Y-m-d');
        $tahunSekarang = now()->year;

        $alphaDates = $hariKerja->filter(function ($tanggal) use ($absensi, $tanggalIzin, $year, $tahunSekarang, $tanggalHariIni) {
            $tanggalParse = Carbon::parse($tanggal);
            $bulan = $tanggalParse->month;
            $tahunAlpha = $tanggalParse->year;

            if ($year == $tahunSekarang) {
                $bulanSekarang = now()->month;
                $tanggalAlphaStr = $tanggalParse->format('Y-m-d');
                if ($bulan > $bulanSekarang || ($bulan == $bulanSekarang && $tanggalAlphaStr > $tanggalHariIni)) {
                    return false;
                }
            }

            return !$absensi->contains($tanggal) && !in_array($tanggal, $tanggalIzin);
        });

        $alphaPerBulan = $alphaDates->groupBy(function ($tanggal) {
            return Carbon::parse($tanggal)->locale('id')->translatedFormat('F Y');
        });

        return [$alphaPerBulan];
    }

    public function sanksi($id)
    {
        $pegawai = Pegawai::findOrFail($id);
        $alpha = Sanksi::where('pegawai_id', $pegawai->id)->get();
        return view('kedisiplinan::alpha.sanksi', compact('pegawai', 'alpha'));
    }
}
