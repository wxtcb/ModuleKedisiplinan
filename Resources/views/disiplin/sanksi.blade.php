@extends('adminlte::page')

@section('title', 'Kedisiplinan')

@section('content_header')
@stop

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Rekapitulasi Ketidakhadiran Pegawai</h3>
                    <a href="{{ route('disiplin.create', ['id' => $pegawai->id]) }}" class="btn btn-primary">Tambahkan Sanksi</a>
                </div>

                <div class="mt-2">
                    @include('layouts.partials.messages')
                </div>

                <table class="table table-bordered table-striped table-sm">
                    <thead class="text-center">
                        <tr>
                            <th>No</th>
                            <th>NIP</th>
                            <th>Pelanggaran <br> Terdeteksi</th>
                            <th>Rekomendasi <br> Sanksi</th>
                            <th>Sanksi Didapat</th>
                            <th>Alasan</th>
                            <th>Tanggal <br> Pemeriksaan</th>
                            <th>BAP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($disiplin as $index => $item)
                            <tr class="text-center align-middle">
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $pegawai->nip }}</td>
                                <td>{{ $item->pelanggaran }}</td>
                                <td>{{ $item->rekomendasi_sanksi }}</td>
                                <td>{{ $item->sanksi }}</td>
                                <td>{{ $item->alasan }}</td>
                                <td>{{ \Carbon\Carbon::parse($item->tanggal_pemeriksaan)->format('d M Y') }}</td>
                                <td>
                                    @if ($item->BAP)
                                        <a href="{{ asset('storage/' . $item->BAP) }}" target="_blank" class="btn btn-sm btn-info">Lihat</a>
                                    @else
                                        <span class="text-muted">Tidak Ada</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">Belum ada data sanksi</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>
@stop
