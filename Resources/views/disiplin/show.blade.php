@extends('adminlte::page')
@section('title', 'Kedisiplinan')
@section('content_header')
    <h1 class="m-0 text-dark">Rekapitulasi Disiplin Jam Kerja Pegawai</h1>
@stop
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-clock"></i> Data Disiplin Pegawai</h3>
                    <div class="card-tools">
                        <a href="{{ route('disiplin.index') }}" class="btn btn-sm btn-secondary">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @include('layouts.partials.messages')

                    <!-- Employee Summary Card -->
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user-tie"></i> Profil Pegawai</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-4">Nama</dt>
                                        <dd class="col-sm-8">{{ $pegawai->nama }}</dd>

                                        <dt class="col-sm-4">NIP</dt>
                                        <dd class="col-sm-8">{{ $pegawai->nip }}</dd>

                                        <dt class="col-sm-4">Jabatan</dt>
                                        <dd class="col-sm-8">
                                            @if (in_array('dosen', $roles))
                                                <span class="badge bg-primary">Dosen</span>
                                            @else
                                                <span class="badge bg-secondary">Staff</span>
                                            @endif
                                        </dd>

                                        <dt class="col-sm-4">Kewajiban Jam</dt>
                                        <dd class="col-sm-8">
                                            <span
                                                class="badge bg-indigo">{{ in_array('dosen', $roles) ? '4 jam/hari' : '8 jam/hari' }}</span>
                                        </dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box bg-light">
                                        <span class="info-box-icon bg-danger"><i class="far fa-clock"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Total Hari Kurang Jam</span>
                                            <span class="info-box-number">{{ count($tanggalKurangJam) }} hari</span>
                                            <div class="progress">
                                                <div class="progress-bar bg-danger"
                                                    style="width: {{ min((count($tanggalKurangJam) / $hariKerja->count()) * 100, 100) }}%">
                                                </div>
                                            </div>
                                            <span class="progress-description">
                                                {{ round((count($tanggalKurangJam) / $hariKerja->count()) * 100, 2) }}% dari total
                                                {{ $hariKerja->count() }} hari kerja
                                            </span>
                                        </div>
                                    </div>

                                    <div class="callout callout-warning mt-3">
                                        <h5><i class="fas fa-info-circle"></i> Keterangan</h5>
                                        <p>
                                            Data ini menampilkan rekapitulasi ketidakteraturan jam kerja selama tahun
                                            {{ now()->year }}.
                                            Hari libur dan cuti resmi tidak termasuk dalam perhitungan.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3>{{ $totalKurangJam }} jam</h3>
                                    <p>Total Kekurangan Jam</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-business-time"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3>{{ $absensiTidakLengkap }} hari</h3>
                                    <p>Absen Tidak Lengkap</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3>{{ $bulanTerburuk }}</h3>
                                    <p>Bulan dengan Ketidakteraturan Terbanyak</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-calendar-times"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Details Table -->
                    <div class="card card-outline card-primary mt-4">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Detail Ketidakhadiran</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm">
                                    <thead class="thead-light text-center">
                                        <tr>
                                            <th style="width: 5%">No</th>
                                            <th style="width: 12%">Tanggal</th>
                                            <th style="width: 10%">Jam Masuk</th>
                                            <th style="width: 10%">Jam Pulang</th>
                                            <th style="width: 12%">Jam Kerja</th>
                                            <th style="width: 12%">Kewajiban</th>
                                            <th style="width: 12%">Kekurangan</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $totalKurangJam = 0;
                                            $absensiTidakLengkap = 0;
                                            $bulanCounts = [];
                                        @endphp

                                        @foreach ($tanggalKurangJam as $index => $item)
                                            @php
                                                $totalKurangJam += $item['kurang'];
                                                if ($item['keterangan'] == 'Absen tidak lengkap') {
                                                    $absensiTidakLengkap++;
                                                }
                                                $bulan = Carbon\Carbon::parse($item['tanggal'])->format('F');
                                                $bulanCounts[$bulan] = ($bulanCounts[$bulan] ?? 0) + 1;
                                            @endphp
                                            <tr
                                                class="{{ $item['kurang'] > ($isDosen ? 2 : 4) ? 'table-danger' : 'table-warning' }}">
                                                <td class="text-center">{{ $index + 1 }}</td>
                                                <td class="text-center">
                                                    {{ \Carbon\Carbon::parse($item['tanggal'])->translatedFormat('d F Y') }}
                                                </td>
                                                <td class="text-center">{{ $item['jam_masuk'] }}</td>
                                                <td class="text-center">{{ $item['jam_pulang'] }}</td>
                                                <td class="text-center">{{ number_format($item['jam_kerja'], 2) }} jam</td>
                                                <td class="text-center">{{ $item['kewajiban'] }} jam</td>
                                                <td class="text-center font-weight-bold">
                                                    {{ number_format($item['kurang'], 2) }} jam</td>
                                                <td>
                                                    @if ($item['keterangan'] == 'Absen tidak lengkap')
                                                        <span class="badge bg-danger">{{ $item['keterangan'] }}</span>
                                                    @else
                                                        <span class="badge bg-warning">{{ $item['keterangan'] }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach

                                        @php
                                            arsort($bulanCounts);
                                            $bulanTerburuk = key($bulanCounts) ?? '-';
                                        @endphp
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer clearfix">
                            <div class="float-left">
                                <a href="{{ route('disiplin.export', $pegawai->id) }}"
                                    class="btn btn-sm btn-success ml-2">
                                    <i class="fas fa-file-excel"></i> Export Excel
                                </a>
                            </div>
                            <div class="float-right">
                                <small class="text-muted">Data diperbarui:
                                    {{ now()->translatedFormat('l, d F Y H:i') }}</small>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .info-box-text {
            font-size: 14px;
            font-weight: bold;
        }

        .info-box-number {
            font-size: 24px;
            font-weight: bold;
        }

        dt {
            font-weight: 600;
            color: #555;
        }

        .table th {
            background-color: #f8f9fa;
        }

        .table-danger {
            background-color: #f8d7da !important;
        }

        .table-warning {
            background-color: #fff3cd !important;
        }

        .small-box .icon {
            font-size: 70px;
        }

        .small-box:hover .icon {
            font-size: 75px;
        }
    </style>
@stop

@section('adminlte_js')
    <script>
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();

            // Initialize DataTable if needed
            $('table').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
                "order": [
                    [1, 'asc']
                ],
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Indonesian.json"
                }
            });
        });
    </script>
@stop
