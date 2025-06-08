<?php

namespace Modules\Kedisiplinan\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Pengaturan\Entities\Pegawai;

class Disiplin extends Model
{
    use HasFactory;
    protected $table = 'sanksi_jam';
    protected $primaryKey = 'id';
    protected $fillable = ['pegawai_id', 'pelanggaran', 'sanksi', 'rekomendasi_sanksi', 'alasan','tanggal_pemeriksaan', 'BAP'];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id', 'id');
    }
}
