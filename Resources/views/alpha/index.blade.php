@extends('adminlte::page')

@section('title', 'Kedisiplinan')

@section('content_header')
    <h1 class="m-0 text-dark">Rekapitulasi Ketidakhadiran Pegawai</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="mt-2">
                        @include('layouts.partials.messages')
                    </div>

                    <table class="table table-bordered table-striped table-sm">
                        <thead class="text-center">
                            <tr>
                                <th rowspan="2">No</th>
                                <th rowspan="2">NIP</th>
                                <th rowspan="2">Nama</th>
                                <th colspan="12">Bulan</th>
                                <th rowspan="2">Ketidakhadiran <br>(Satu Tahun)</th>
                                <th rowspan="2">Jumlah <br>Sanksi</th>
                            </tr>
                            <tr>
                                @foreach (['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'] as $bulan)
                                    <th>{{ $bulan }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rekapData as $index => $data)
                                <tr class="text-center align-middle">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $data['nip'] }}</td>
                                    <td class="text-left">{{ $data['nama'] }}</td>
                                    @foreach (range(1, 12) as $bulan)
                                        <td>
                                            @if ($bulan > date('n'))
                                                -
                                            @else
                                                {{ $data['tm_per_bulan'][$bulan] ?? 0 }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td><a href="{{route('alpha.show', ['id' => $data['id']])}}">
                                        {{ collect($data['tm_per_bulan'])->filter(function ($value, $key) {
                                            return $key <= date('n') && is_numeric($value);
                                        })->sum() }} Hari
                                    </a>
                                        
                                    </td>
                                    <td class="text-center">-</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="17" class="text-center">Tidak ada data</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
@stop
