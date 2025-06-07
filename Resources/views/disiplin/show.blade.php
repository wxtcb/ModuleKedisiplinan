@extends('adminlte::page')
@section('title', 'Kedisiplinan')
@section('content_header')
<h1 class="m-0 text-dark"></h1>
@stop
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h3>Rekapitulasi Disiplin Jam Kerja Pegawai</h3>
                <div class="lead">

                </div>

                <div class="mt-2">
                    @include('layouts.partials.messages')
                </div>

                <div class="mb-3">
                    <strong>NIP:</strong> {{ $pegawai->nip }} <br>
                    <strong>Nama:</strong> {{ $pegawai->nama }} <br>
                    <strong>Total Hari Kurang Jam Tahun Ini:</strong> {{ count($tanggalKurangJam) }} hari
                </div>

                <table class="table table-bordered table-striped table-sm">
                    <thead class="text-center">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Pulang</th>
                            <th>Jumlah Jam Kerja</th>
                            <th>Kewajiban Jam Kerja</th>
                            <th>Jam Kerja Kurang</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tanggalKurangJam as $index => $item)
                        <tr>
                            <td class="text-center">{{ $index + 1 }}</td>
                            <td>{{ \Carbon\Carbon::parse($item['tanggal'])->format('d-m-Y') }}</td>
                            <td>{{ $item['jam_masuk'] }}</td>
                            <td>{{ $item['jam_pulang'] }}</td>
                            <td class="text-center">{{ $item['jam_kerja'] }} jam</td>
                            <td class="text-center">{{ $item['kewajiban'] }} jam</td>
                            <td class="text-center">{{ $item['kurang'] }} jam</td>
                            <td>{{ $item['keterangan'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>


            </div>
        </div>
    </div>
</div>
@stop