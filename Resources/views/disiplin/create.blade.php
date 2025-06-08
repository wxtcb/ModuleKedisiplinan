@extends('adminlte::page')
@section('title', 'Tambah Kedisiplinan Pegawai')
@section('css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
@endsection
@section('content_header')
    <h1 class="m-0 text-dark">Form Kedisiplinan Pegawai</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12">
            <!-- Form Surat Izin -->
            <div class="card">
                <div class="card-body">
                    <div class="mt-2">
                        @include('layouts.partials.messages')
                        @if (session('error'))
                            <div class="alert alert-warning" role="alert">
                                {{ session('error') }}
                            </div>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('disiplin.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="pegawai_id" value="{{ $pegawai->id }}">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nama" class="form-label">Nama</label>
                                <input type="text" class="form-control" value="{{ $pegawai->nama }}" readonly required>
                            </div>
                            <div class="col-md-6">
                                <label for="nip" class="form-label">NIP/NIK/NIPPK</label>
                                <input type="text" class="form-control" value="{{ $pegawai->nip }}" readonly required>
                            </div>
                        </div>
                        <div>
                            <label for="unit" class="form-label">Unit</label>
                            <input type="text" class="form-control" value="{{ $pegawai->id_staff }}" readonly required>
                        </div>
                        <div class="row mb-3 mt-3">
                            <div class="col-md-6">
                                <label for="pilih_bulan" class="form-label">Pilih Bulan</label>
                                <input type="text" id="bulan_range" name="bulan_range" class="form-control"
                                    placeholder="Pilih rentang bulan">
                            </div>
                            <div class="col-md-6">
                                <label for="pelanggaran" class="form-label">Pelanggaran</label>
                                <input type="text" id="pelanggaran" name="pelanggaran" class="form-control" required>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label for="rekomendasi_sanksi" class="form-label">Rekomendasi Sanksi</label>
                            <div class="input-group">
                                <input type="text" id="rekomendasi_sanksi" name="rekomendasi_sanksi" class="form-control"
                                    required>
                                <button type="button" class="btn btn-outline-secondary" id="btn-copy-sanksi">
                                    Gunakan sebagai sanksi
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 mb-3">
                            <label for="sanksi" class="form-label">Sanksi Diberikan</label>
                            <input type="text" name="sanksi" class="form-control" required>
                        </div>
                        <div class="mt-3 mb-3">
                            <label for="alasan" class="form-label">Alasan</label>
                            <input type="text" name="alasan" class="form-control" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tanggal_pemeriksaan" class="form-label">Tanggal Pemeriksaan</label>
                                <input type="date" name="tanggal_pemeriksaan" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="BAP" class="form-label">BAP</label>
                                <input type="file" name="BAP" class="form-control" accept=".pdf" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('alpha.index') }}" class="btn btn-default">Kembali</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@stop
@section('adminlte_js')
    <!-- Flatpickr core -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- Month select plugin -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <script>
        flatpickr("#bulan_range", {
            dateFormat: "Y-m",
            plugins: [
                new monthSelectPlugin({
                    shorthand: true,
                    dateFormat: "Y-m",
                    altFormat: "F Y"
                })
            ],
            mode: "range"
        });

        flatpickr('#rentang_sanksi', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            allowInput: true,
        });

        function tentukanSanksi(jumlah) {
            if (jumlah === 3) {
                return 'Teguran Lisan';
            } else if (jumlah >= 4 && jumlah <= 6) {
                return 'Teguran Tertulis';
            } else if (jumlah >= 7 && jumlah <= 10) {
                return 'Pernyataan Tidak Puas Tertulis';
            } else if (jumlah >= 11 && jumlah <= 13) {
                return 'Potongan Tunjangan 25% Selama 6 Bulan';
            } else if (jumlah >= 14 && jumlah <= 16) {
                return 'Potongan Tunjangan 25% Selama 9 Bulan';
            } else if (jumlah >= 17 && jumlah <= 20) {
                return 'Potongan Tunjangan 25% Selama 12 Bulan';
            } else if (jumlah >= 21 && jumlah <= 24) {
                return 'Penurunan Jabatan Setingkat Lebih Rendah Selama 12 Bulan';
            } else if (jumlah >= 25 && jumlah <= 27) {
                return 'Pembebasan Jabatan Menjadi Jabatan Pelaksana Selama 12 Bulan';
            } else if (jumlah >= 28) {
                return 'Pemberhentian Dengan Hormat Tidak Atas Permintaan Sendiri Tanpa Alasan';
            }
            return '';
        }

        document.getElementById('bulan_range').addEventListener('change', function() {
            const range = this.value;
            const pegawai_id = {{ $pegawai->id }};

            let start, end;
            if (range.includes(" to ")) {
                [start, end] = range.split(" to ");
            } else {
                start = end = range;
            }

            if (start && end) {
                fetch('{{ route('disiplin.hitung_tidak_hadir') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            bulan_range: `${start} to ${end}`,
                            pegawai_id: pegawai_id
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        const jumlah = data.jumlah;
                        document.getElementById('pelanggaran').value = `${jumlah} hari tidak hadir`;

                        const rekomendasi = tentukanSanksi(jumlah);
                        document.querySelector('input[name="rekomendasi_sanksi"]').value = rekomendasi;
                    })
                    .catch(err => {
                        console.error('Gagal menghitung tidak hadir:', err);
                    });
            }
        });
    </script>
    <script>
        document.getElementById('btn-copy-sanksi').addEventListener('click', function() {
            const rekomendasi = document.getElementById('rekomendasi_sanksi').value;
            const sanksiInput = document.querySelector('input[name="sanksi"]');
            if (rekomendasi && sanksiInput) {
                sanksiInput.value = rekomendasi;
            }
        });
    </script>
@stop
