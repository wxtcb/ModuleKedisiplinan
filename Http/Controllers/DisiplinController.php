<?php

namespace Modules\Kedisiplinan\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Cuti\Entities\Cuti;
use Modules\Kedisiplinan\Entities\Alpha;
use Modules\Kedisiplinan\Entities\disiplin;
use Modules\Kedisiplinan\Exports\DisiplinExport;
use Modules\Pengaturan\Entities\Pegawai;
use Modules\Setting\Entities\Libur;
use Modules\SuratIjin\Entities\LupaAbsen;
use Modules\SuratIjin\Entities\Terlambat;
use Modules\SuratTugas\Entities\SuratTugas;

class DisiplinController extends Controller
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
            $tmPerBulan = array_fill(1, 12, 0); // Inisialisasi tetap 12 bulan

            // Ambil role pegawai (asumsinya role tetap diakses via user/username)
            $roles = DB::table('model_has_roles')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->join('users', 'model_has_roles.model_id', '=', 'users.id')
                ->where('users.username', $pegawai->username)
                ->pluck('roles.name')
                ->toArray();

            $isDosen = in_array('dosen', $roles);
            $batasMinimalJam = $isDosen ? 4 : 8;

            foreach ($hariKerja as $tanggal) {
                $bulan = \Carbon\Carbon::parse($tanggal)->month;

                if ($bulan === $bulanSekarang && $tanggal > $tanggalHariIni) continue;
                if ($bulan > $bulanSekarang) continue;

                if (
                    in_array($tanggal, $dinasLuarByPegawai[$pegawai->id] ?? []) ||
                    in_array($tanggal, $cutiByPegawai[$pegawai->id] ?? [])
                ) {
                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;
                $absensiHariIni = $kehadiran[$key] ?? collect();

                // Skip hari kerja jika tidak ada presensi sama sekali
                if ($absensiHariIni->isEmpty()) continue;

                // Cek surat izin sah
                $izinAda = Terlambat::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->exists()
                    || LupaAbsen::where('pegawai_id', $pegawai->id)
                    ->where('status', 'Disetujui')
                    ->whereDate('tanggal', $tanggal)
                    ->exists();

                $sortedTimes = $absensiHariIni->sortBy('checktime')->pluck('checktime')->map(fn($ct) => \Carbon\Carbon::parse($ct));
                $jamMasuk = $sortedTimes->first();
                $jamPulang = $sortedTimes->last();
                $jumlahAbsen = $sortedTimes->count();
                $durasiKerja = $jamMasuk->diffInMinutes($jamPulang) / 60;

                // Kondisi: presensi kurang (cuma masuk atau cuma pulang), atau durasi kerja tidak memenuhi, dan tidak ada izin
                $tidakLengkap = $jumlahAbsen < 2 || $durasiKerja < $batasMinimalJam;

                if ($tidakLengkap && !$izinAda) {
                    $tmPerBulan[$bulan]++;
                }
            }

            return [
                'id' => $pegawai->id,
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'tm_per_bulan' => $tmPerBulan,
            ];
        })->toArray(); // Tidak pakai filter(), agar semua pegawai tetap ditampilkan

        return view('kedisiplinan::disiplin.index', compact('rekapData', 'year'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create($id)
    {
        $pegawai = Pegawai::findOrFail($id);
        return view('kedisiplinan::disiplin.create', compact('pegawai'));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $pegawai = Pegawai::findOrFail($id);

        $year = now()->year;
        $bulanSekarang = now()->month;
        $tanggalHariIni = now()->toDateString();

        // Get user roles
        $roles = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->join('users', 'model_has_roles.model_id', '=', 'users.id')
            ->where('users.username', $pegawai->username)
            ->pluck('roles.name')
            ->toArray();

        $isDosen = in_array('dosen', $roles);

        // Libur
        $tanggalLibur = Libur::whereYear('tanggal', $year)
            ->pluck('tanggal')
            ->map(fn($t) => Carbon::parse($t)->format('Y-m-d'))
            ->toArray();

        // Kehadiran
        $kehadiran = Alpha::whereYear('checktime', $year)
            ->where('user_id', $pegawai->id)
            ->get()
            ->groupBy(fn($item) => $pegawai->id . '|' . Carbon::parse($item->checktime)->format('Y-m-d'));

        // Cuti
        $cuti = Cuti::where('status', 'Selesai')
            ->where('pegawai_id', $pegawai->id)
            ->get();

        $cutiByPegawai = [];
        foreach ($cuti as $item) {
            $start = Carbon::parse($item->tanggal_mulai);
            $end = Carbon::parse($item->tanggal_selesai);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($date->year == $year) {
                    $cutiByPegawai[$pegawai->id][] = $date->format('Y-m-d');
                }
            }
        }

        // Dinas Luar
        $suratTugas = SuratTugas::with(['detail', 'anggota'])
            ->whereHas('detail', fn($q) => $q->whereYear('tanggal_mulai', '<=', $year)->orWhereYear('tanggal_selesai', '<=', $year))
            ->get();

        $dinasLuarByPegawai = [];
        foreach ($suratTugas as $surat) {
            if (!$surat->detail) continue;
            $start = Carbon::parse($surat->detail->tanggal_mulai);
            $end = Carbon::parse($surat->detail->tanggal_selesai);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $tgl = $date->format('Y-m-d');
                if ($date->year != $year) continue;
                if ($surat->detail->pegawai_id == $pegawai->id) {
                    $dinasLuarByPegawai[$pegawai->id][] = $tgl;
                }
                foreach ($surat->anggota as $anggota) {
                    if ($anggota->pegawai_id == $pegawai->id) {
                        $dinasLuarByPegawai[$pegawai->id][] = $tgl;
                    }
                }
            }
        }

        // Hari kerja
        $hariKerja = collect();
        $start = Carbon::create($year, 1, 1);
        $end = Carbon::create($year, 12, 31);
        while ($start <= $end) {
            $tgl = $start->format('Y-m-d');
            if (!$start->isWeekend() && !in_array($tgl, $tanggalLibur)) {
                $hariKerja->push($tgl);
            }
            $start->addDay();
        }

        // Ambil detail tanggal yang kurang jam
        $tanggalKurangJam = $this->hitungTanggalJamKurang(
            $pegawai,
            $kehadiran,
            $hariKerja,
            $cutiByPegawai,
            $dinasLuarByPegawai,
            $bulanSekarang,
            $tanggalHariIni
        );

        // Calculate statistics
        $totalKurangJam = collect($tanggalKurangJam)->sum('kurang');
        $absensiTidakLengkap = collect($tanggalKurangJam)->where('keterangan', 'Absen tidak lengkap')->count();

        $bulanCounts = [];
        foreach ($tanggalKurangJam as $item) {
            $bulan = Carbon::parse($item['tanggal'])->format('F');
            $bulanCounts[$bulan] = ($bulanCounts[$bulan] ?? 0) + 1;
        }
        arsort($bulanCounts);
        $bulanTerburuk = key($bulanCounts) ?? '-';

        return view('kedisiplinan::disiplin.show', compact(
            'pegawai',
            'tanggalKurangJam',
            'hariKerja',
            'roles',
            'totalKurangJam',
            'absensiTidakLengkap',
            'bulanTerburuk',
            'isDosen'
        ));
    }

    private function hitungTanggalJamKurang($pegawai, $kehadiran, $hariKerja, $cutiByPegawai, $dinasLuarByPegawai, $bulanSekarang, $tanggalHariIni)
    {
        $tanggalKurangJam = [];

        $roles = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->join('users', 'model_has_roles.model_id', '=', 'users.id')
            ->where('users.username', $pegawai->username)
            ->pluck('roles.name')
            ->toArray();

        $isDosen = in_array('dosen', $roles);
        $batasMinimalJam = $isDosen ? 4 : 8;

        foreach ($hariKerja as $tanggal) {
            $bulan = Carbon::parse($tanggal)->month;

            if ($bulan === $bulanSekarang && $tanggal > $tanggalHariIni) continue;
            if ($bulan > $bulanSekarang) continue;

            if (
                in_array($tanggal, $cutiByPegawai[$pegawai->id] ?? []) ||
                in_array($tanggal, $dinasLuarByPegawai[$pegawai->id] ?? [])
            ) continue;

            $key = $pegawai->id . '|' . $tanggal;
            $absensi = $kehadiran[$key] ?? collect();

            if ($absensi->isEmpty()) continue;

            $izinAda = Terlambat::where('pegawai_id', $pegawai->id)
                ->where('status', 'Disetujui')
                ->whereDate('tanggal', $tanggal)
                ->exists()
                || LupaAbsen::where('pegawai_id', $pegawai->id)
                ->where('status', 'Disetujui')
                ->whereDate('tanggal', $tanggal)
                ->exists();

            $sorted = $absensi->sortBy('checktime')->pluck('checktime')->map(fn($ct) => Carbon::parse($ct));
            $jumlahAbsen = $sorted->count();
            $jamMasuk = $sorted->first();
            $jamPulang = $sorted->last();
            $durasiKerja = $jumlahAbsen >= 2 ? $jamMasuk->diffInMinutes($jamPulang) / 60 : 0;

            $tidakLengkap = $jumlahAbsen < 2 || $durasiKerja < $batasMinimalJam;

            if ($tidakLengkap && !$izinAda) {
                $tanggalKurangJam[] = [
                    'tanggal' => $tanggal,
                    'jam_masuk' => $jamMasuk?->format('H:i') ?? '-',
                    'jam_pulang' => $jamPulang?->format('H:i') ?? '-',
                    'jam_kerja' => round($durasiKerja, 2),
                    'kewajiban' => $batasMinimalJam,
                    'kurang' => round(max($batasMinimalJam - $durasiKerja, 0), 2),
                    'keterangan' => $jumlahAbsen < 2 ? 'Absen tidak lengkap' : 'Jam kerja kurang',
                ];
            }
        }

        return $tanggalKurangJam;
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

    public function export($id)
    {
        $pegawai = Pegawai::findOrFail($id);

        // Reuse the same logic from your show method to get the data
        $year = now()->year;
        $bulanSekarang = now()->month;
        $tanggalHariIni = now()->toDateString();

        // Get user roles
        $roles = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->join('users', 'model_has_roles.model_id', '=', 'users.id')
            ->where('users.username', $pegawai->username)
            ->pluck('roles.name')
            ->toArray();

        $isDosen = in_array('dosen', $roles);

        // Libur
        $tanggalLibur = Libur::whereYear('tanggal', $year)
            ->pluck('tanggal')
            ->map(fn($t) => Carbon::parse($t)->format('Y-m-d'))
            ->toArray();

        // Kehadiran
        $kehadiran = Alpha::whereYear('checktime', $year)
            ->where('user_id', $pegawai->id)
            ->get()
            ->groupBy(fn($item) => $pegawai->id . '|' . Carbon::parse($item->checktime)->format('Y-m-d'));

        // Cuti
        $cuti = Cuti::where('status', 'Selesai')
            ->where('pegawai_id', $pegawai->id)
            ->get();

        $cutiByPegawai = [];
        foreach ($cuti as $item) {
            $start = Carbon::parse($item->tanggal_mulai);
            $end = Carbon::parse($item->tanggal_selesai);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if ($date->year == $year) {
                    $cutiByPegawai[$pegawai->id][] = $date->format('Y-m-d');
                }
            }
        }

        // Dinas Luar
        $suratTugas = SuratTugas::with(['detail', 'anggota'])
            ->whereHas('detail', fn($q) => $q->whereYear('tanggal_mulai', '<=', $year)->orWhereYear('tanggal_selesai', '<=', $year))
            ->get();

        $dinasLuarByPegawai = [];
        foreach ($suratTugas as $surat) {
            if (!$surat->detail) continue;
            $start = Carbon::parse($surat->detail->tanggal_mulai);
            $end = Carbon::parse($surat->detail->tanggal_selesai);
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $tgl = $date->format('Y-m-d');
                if ($date->year != $year) continue;
                if ($surat->detail->pegawai_id == $pegawai->id) {
                    $dinasLuarByPegawai[$pegawai->id][] = $tgl;
                }
                foreach ($surat->anggota as $anggota) {
                    if ($anggota->pegawai_id == $pegawai->id) {
                        $dinasLuarByPegawai[$pegawai->id][] = $tgl;
                    }
                }
            }
        }

        // Hari kerja
        $hariKerja = collect();
        $start = Carbon::create($year, 1, 1);
        $end = Carbon::create($year, 12, 31);
        while ($start <= $end) {
            $tgl = $start->format('Y-m-d');
            if (!$start->isWeekend() && !in_array($tgl, $tanggalLibur)) {
                $hariKerja->push($tgl);
            }
            $start->addDay();
        }

        // Get the data
        $tanggalKurangJam = $this->hitungTanggalJamKurang(
            $pegawai,
            $kehadiran,
            $hariKerja,
            $cutiByPegawai,
            $dinasLuarByPegawai,
            $bulanSekarang,
            $tanggalHariIni
        );

        // Calculate statistics
        $totalKurangJam = collect($tanggalKurangJam)->sum('kurang');
        $absensiTidakLengkap = collect($tanggalKurangJam)->where('keterangan', 'Absen tidak lengkap')->count();

        $bulanCounts = [];
        foreach ($tanggalKurangJam as $item) {
            $bulan = Carbon::parse($item['tanggal'])->format('F');
            $bulanCounts[$bulan] = ($bulanCounts[$bulan] ?? 0) + 1;
        }
        arsort($bulanCounts);
        $bulanTerburuk = key($bulanCounts) ?? '-';

        $filename = 'disiplin_' . strtolower(str_replace(' ', '_', $pegawai->nama)) . '_' . $year . '.xlsx';

        return Excel::download(new DisiplinExport(
            $pegawai,
            $tanggalKurangJam,
            $totalKurangJam,
            $absensiTidakLengkap,
            $bulanTerburuk
        ), $filename);
    }

    public function sanksi($id)
    {
        $pegawai = Pegawai::findOrFail($id);
        $disiplin = Disiplin::where('pegawai_id', $pegawai->id)->get();
        return view('kedisiplinan::disiplin.sanksi', compact('pegawai', 'disiplin'));
    }
}
