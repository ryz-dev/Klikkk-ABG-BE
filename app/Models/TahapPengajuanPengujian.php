<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahapPengajuanPengujian extends Model
{
    protected $table = 'tahap_pengajuan_pengujian';
    protected $fillable = ['uuid', 'nama', 'urutan', 'pic'];

    public function user(){
        return $this->belongsTo('App\Models\User','pic','id');
    }
}
