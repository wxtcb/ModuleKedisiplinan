<?php

namespace Modules\Kedisiplinan\Http\Controllers;

use App\Models\Core\User;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cuti\Entities\Cuti;
use Modules\Kedisiplinan\Entities\Alpha;
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
                if (in_array($tanggal, $dinasLuarByPegawai[$pegawai->id] ?? []) ||
                    in_array($tanggal, $cutiByPegawai[$pegawai->id] ?? [])) {
                    continue;
                }

                $key = $pegawai->id . '|' . $tanggal;

                if ($kehadiran->has($key)) {
                    $absenHariItu = $kehadiran->get($key);
                    $datang = $absenHariItu->where('checktype', 'I')->sortBy('checktime')->first();
                    $pulang = $absenHariItu->where('checktype', 'O')->sortByDesc('checktime')->first();

                    $hasI = $datang !== null;
                    $hasO = $pulang !== null;

                    $izinMasukDisetujui = Terlambat::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->where('jenis_ijin', 'Terlambat')
                        ->exists() ||
                        LupaAbsen::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->where('jenis_ijin', 'Lupa Absen Masuk')
                        ->exists();

                    $izinPulangDisetujui = Terlambat::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->where('jenis_ijin', 'Pulang Cepat')
                        ->exists() ||
                        LupaAbsen::where('pegawai_id', $pegawai->id)
                        ->where('status', 'Disetujui')
                        ->whereDate('tanggal', $tanggal)
                        ->where('jenis_ijin', 'Lupa Absen Pulang')
                        ->exists();

                    $userPegawai = User::where('username', $pegawai->username)->first();
                    $pegawaiRoles = $userPegawai?->getRoleNames()?->toArray() ?? [];
                    $jenis = in_array('dosen', $pegawaiRoles) ? 'dosen' : 'pegawai';
                    $minimalJam = ($jenis === 'dosen') ? 4 : 8;

                    $jamKerjaCustom = Jam::where('jenis', $jenis)
                        ->whereDate('tanggal_mulai', '<=', $tanggal)
                        ->whereDate('tanggal_selesai', '>=', $tanggal)
                        ->first();

                    if ($jamKerjaCustom && !empty($jamKerjaCustom->jam_kerja)) {
                        $jamKerjaStr = strtolower(trim($jamKerjaCustom->jam_kerja));
                        $jamKerjaStr = preg_replace('/\s+/', ' ', $jamKerjaStr);
                        if (preg_match('/(\d+)\s*jam\s*(\d+)\s*menit/', $jamKerjaStr, $matches)) {
                            $minimalJam = (int)$matches[1] + ((int)$matches[2] / 60);
                        }
                    }

                    if ($hasI && $hasO) {
                        $jamKerjaJam = strtotime($pulang->checktime) - strtotime($datang->checktime);
                        $jamKerjaJam /= 3600;

                        if ($jamKerjaJam >= $minimalJam || $izinMasukDisetujui || $izinPulangDisetujui) {
                            continue; // Dianggap hadir, tidak dihitung sebagai TM
                        } else {
                            $tmPerBulan[$bulan]++;
                        }
                    } elseif ($hasI || $hasO) {
                        if (($hasI && $izinPulangDisetujui) || ($hasO && $izinMasukDisetujui)) {
                            continue; // Dianggap hadir, tidak dihitung sebagai TM
                        } else {
                            $tmPerBulan[$bulan]++;
                        }
                    } else {
                        $izinLupaMasuk = LupaAbsen::where('pegawai_id', $pegawai->id)
                            ->where('status', 'Disetujui')
                            ->whereDate('tanggal', $tanggal)
                            ->where('jenis_ijin', 'Lupa Absen Masuk')
                            ->exists();

                        $izinLupaPulang = LupaAbsen::where('pegawai_id', $pegawai->id)
                            ->where('status', 'Disetujui')
                            ->whereDate('tanggal', $tanggal)
                            ->where('jenis_ijin', 'Lupa Absen Pulang')
                            ->exists();

                        if ($izinLupaMasuk && $izinLupaPulang) {
                            continue; // Dianggap hadir, tidak dihitung sebagai TM
                        } else {
                            $tmPerBulan[$bulan]++;
                        }
                    }
                } else {
                    $tmPerBulan[$bulan]++;
                }
            }

            return [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'tm_per_bulan' => $tmPerBulan
            ];
        })->toArray();

        return view('kedisiplinan::alpha.index', compact('rekapData', 'year'));

    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('kedisiplinan::create');
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
        return view('kedisiplinan::show');
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
}
