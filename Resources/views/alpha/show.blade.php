@extends('adminlte::page')

@php use Carbon\Carbon; @endphp

@section('title', 'Detail Ketidakhadiran Pegawai')

@section('content_header')
<h1 class="m-0 text-dark">Detail Ketidakhadiran Pegawai</h1>
@stop

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <!-- Informasi Pegawai -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <strong>NIP:</strong> {{ $pegawai->nip }}
                    </div>
                    <div class="col-md-5">
                        <strong>Nama:</strong> {{ $pegawai->nama }}
                    </div>

                    <div class="col-md-3 text-md-right">
                        <strong>Total Alpha (Tahun {{ $year }}):</strong>
                        {{ $alphaPerBulan->flatten()->count() }} Hari
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kalender Tahunan -->
    <div class="col-12">
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
                <h5 class="mb-0">Kalender Alpha Tahun {{ $year }}</h5>

                <!-- Filter Tahun -->
                <div>
                    <form action="{{ route('alpha.show', ['id' => $pegawai->id]) }}" method="GET" class="form-inline">
                        <label for="year" class="mr-2">Tahun:</label>
                        <select name="year" id="year" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                            @php
                            $tahunSekarang = now()->year;
                            $startYear = $tahunSekarang - 2; // 2 tahun ke belakang
                            @endphp

                            @for ($tahun = $startYear; $tahun <= $tahunSekarang; $tahun++)
                                <option value="{{ $tahun }}" {{ $tahun == $year ? 'selected' : '' }}>
                                {{ $tahun }}
                                </option>
                                @endfor
                        </select>
                        <button type="submit" class="btn btn-sm btn-light">Lihat</button>
                    </form>
                </div>
                <a href="{{ route('alpha.export', ['id' => $pegawai->id, 'year' => request('year', now()->year)]) }}"
                    class="btn btn-success btn-sm mt-2">
                    Export Excel
                </a>

            </div>
            <div class="card-body">
                <div class="row">
                    @for ($bulan = 1; $bulan <= 12; $bulan++)
                        @php
                        $namaBulan=Carbon::create()->month($bulan)->year($year)->locale('id')->translatedFormat('F');
                        $keyBulan = "$namaBulan $year";
                        $tanggalDiBulanIni = $alphaPerBulan->get($keyBulan, collect([]));
                        @endphp

                        <div class="col-md-3 mb-4">
                            <div class="card border rounded shadow-sm">
                                <div class="card-header font-weight-bold text-center">
                                    {{ $namaBulan }}
                                </div>
                                <div class="card-body p-2">
                                    @if($tanggalDiBulanIni->isNotEmpty())
                                    <ul class="list-group list-group-flush">
                                        @foreach($tanggalDiBulanIni as $tanggal)
                                        <li class="list-group-item">
                                            {{ Carbon::parse($tanggal)->locale('id')->translatedFormat('d F Y') }}
                                        </li>
                                        @endforeach
                                    </ul>
                                    @else
                                    <p class="text-muted text-center m-0">Tidak ada alpha</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endfor
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('css')
<style>
    .list-group-item.text-danger {
        color: red !important;
        padding: 0.3rem 0.75rem;
        font-size: 0.9rem;
    }

    .card-header {
        background-color: #f8f9fa;
        font-weight: bold;
    }
</style>
@endsection