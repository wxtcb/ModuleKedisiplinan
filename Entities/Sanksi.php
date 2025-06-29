<?php

namespace Modules\Kedisiplinan\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Pengaturan\Entities\Pegawai;

class Sanksi extends Model
{
    use HasFactory;
    protected $table = 'sanksi_alpha';
    protected $primaryKey = 'id';
    protected $fillable = ['pegawai_id', 'pelanggaran', 'sanksi', 'rekomendasi_sanksi', 'alasan', 'tanggal_mulai', 'tanggal_selesai', 'tanggal_pemeriksaan', 'BAP'];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id', 'id');
    }
}
