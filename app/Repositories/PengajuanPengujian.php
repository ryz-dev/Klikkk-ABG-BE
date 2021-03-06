<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Http\Resources\PengajuanPengujian as PengajuanPengujianResource;
use Illuminate\Database\Eloquent\Builder;
use App\Exceptions\PengajuanNotFoundException;

class PengajuanPengujian
{
    public $detailPengajuanPengujian;
    public $masterPengajuanPengujian;
    public $jenisPengujian;
    public $parameterPengujian;
    public $prosesPengajuan;
    public $biayaTambahan;
    public $limit = 10;

    public function __construct($regId = null)
    {

        $this->jenisPengujian = $this->jenisPengujian();
        $this->parameterPengujian = $this->parameterPengujian();
        $this->masterPengajuanPengujian = $this->masterPengajuanPengujian($regId);
        $this->detailPengajuanPengujian = $this->detailPengajuanPengujian();
        $this->prosesPengajuan = $this->prosesPengajuan();
        $this->biayaTambahan = $this->biayaTambahan();
        // dd($this->prosesPengajuan );

        return $this;
    }

    private function jenisPengujian()
    {
        return app()->make('App\Models\JenisPengujian');
    }

    private function parameterPengujian()
    {
        return app()->make('App\Models\ParameterPengujian');
    }

    public function masterPengajuanPengujian($regId = null)
    {

        $masterPengajuanPengujian = app()->make('App\Models\PengajuanPengujian');

        if ($regId) {
            $_masterPengajuanPengujian = $masterPengajuanPengujian->where('regId', $regId);
            return $_masterPengajuanPengujian->first()?$_masterPengajuanPengujian:$masterPengajuanPengujian;
        }

        return $masterPengajuanPengujian;
    }

    public function detailPengajuanPengujian()
    {
        $detailPengajuanPengujian = app()->make('App\Models\DetailPengajuanPengujian');

        if ($this->masterPengajuanPengujian instanceof Builder) {
            return $this->masterPengajuanPengujian->first()->detailPengajuanPengujian;
        }

        return $detailPengajuanPengujian;
    }

    public function prosesPengajuan()
    {
        $prosesPengajuan = app()->make('App\Models\ProsesPengajuan');

        if ($this->masterPengajuanPengujian instanceof Builder) {
            return $this->masterPengajuanPengujian->first()->prosesPengajuan;
        }

        return $prosesPengajuan;
    }

    public function biayaTambahan()
    {
        $biayaTambahan = app()->make('App\Models\BiayaTambahan');

        if ($this->masterPengajuanPengujian instanceof Builder) {
            // if (count($this->masterPengajuanPengujian->first()->biayaTambahan)) {
                return $this->masterPengajuanPengujian->first()->biayaTambahan;
            // }
            // return $biayaTambahan;
        }

        return $biayaTambahan;
    }

    public static function getAllJenisPengajuan()
    {
        $jenis_pengujian = (new self);

        $jenis_pengujian = $jenis_pengujian->jenisPengujian->active()->orderBy('nama');

        $jenis_pengujian = $jenis_pengujian->get()->groupBy(function($value){

            return explode('-',$value->nama)[0];

        })->transform(function($value, $key){
            $value->map(function($value){

                if (count(explode('-',$value->nama)) > 1)  {
                    $value->nama = explode('-',$value->nama)[1];
                }
                return $value;

            });

            return $value;
        });

        $master_jenis_pengujian = [];
        $i = 0;

        foreach ($jenis_pengujian as $key => $value) {
            $master_jenis_pengujian[$i]['group'] = $key;
            $master_jenis_pengujian[$i]['data'] = $value;
            $i++;
        }

        return dtcApiResponse(200,$master_jenis_pengujian);
    }

    public static function getParameterPengujian($data)
    {
        $pengajuanPengujian = (new self);

        $jenisPengujian = $pengajuanPengujian->jenisPengujian->active()->whereIn('id', $data->jenis_pengujian)->with(['parameterPengujian' => function($query){
            return $query->active();
        }])->get();

        return dtcApiResponse(200, $jenisPengujian);
    }

    public function store($data)
    {
        $masterPengajuanPengujian = $this->masterPengajuanPengujian;

        $prosesPengajuan = $this->prosesPengajuan;

        $_masterPengajuan = $data->only($masterPengajuanPengujian->getFillable());

        $masterPengajuanPengujian->uuid = \Str::uuid();
        $masterPengajuanPengujian->regId = $masterPengajuanPengujian->generateRegId();
        $masterPengajuanPengujian->tahap_pengajuan = 1;
        $masterPengajuanPengujian->fill($_masterPengajuan);

        $prosesPengajuan->uuid = \Str::uuid();
        $prosesPengajuan->tahap_pengajuan = 1;
        $prosesPengajuan->tanggal_mulai = \Carbon\Carbon::now();

        $detailPengajuanPengujian = [];
        $i = 0;
        foreach ($data->id_parameter_pengujian as $key => $value) {
            $detailPengajuanPengujian[$i]['id_parameter_pengujian'] = $value;
            $detailPengajuanPengujian[$i]['jumlah_titik'] = $data->jumlah_titik[$i];
            $i++;
        }

        DB::transaction(function() use($masterPengajuanPengujian, $detailPengajuanPengujian, $prosesPengajuan) {
            $masterPengajuanPengujian->save();

            $masterPengajuanPengujian->detailPengajuanPengujian()->createmany($detailPengajuanPengujian);

            $masterPengajuanPengujian->prosesPengajuan()->save($prosesPengajuan);


        });

        return $this::getOne($masterPengajuanPengujian->regId);

    }

    public function update($data)
    {

    }

    public function getListPengajuan($request = null, $tahap)
    {
        $pengajuanPengujian = $this->masterPengajuanPengujian;

        $pengajuanPengujian = $pengajuanPengujian->tahap($tahap);

        $pengajuanPengujian = $request->has('search')?$pengajuanPengujian->where('regId','like','%'.$request->search.'%'):$pengajuanPengujian;

        $pengajuanPengujian = $pengajuanPengujian->active()->latest();

        $pengajuanPengujian = $pengajuanPengujian->paginate($this->limit);

        $pengajuanPengujian->getCollection()->transform(function($value){
            return [
                'nomor_pengajuan' => $value->regId,
                'nama_pemohon' => $value->nama_pemohon,
                'tanggal_pengajuan' => prettyDate($value->created_at),
                'tujuan_pengujian' => $value->tujuan_pengujian,
                'avatar' => userAvatar($value->users->avatar)
            ];
        });

        return $pengajuanPengujian;
    }

    public static function getOne($regId)
    {
        $pengajuanPengujian = new self($regId);

        if ($pengajuanPengujian->masterPengajuanPengujian instanceof Builder) {
            $pengajuanPengujian = $pengajuanPengujian->masterPengajuanPengujian->first();

            $_dataPengujian = $pengajuanPengujian->detailPengajuanPengujian()->with('parameterPengujian')->get()->groupBy('parameterPengujian.jenisPengujian.nama')->map(function($value){
                return $value->map(function($value){
                    return [
                        'id_detail' => $value->id,
                        'id_parameter' => $value->id_parameter_pengujian,
                        'parameter' => $value->parameterPengujian->nama,
                        'jumlah_titik' => $value->jumlah_titik,
                        'biaya' => $value->parameterPengujian->biaya,
                        'total' => $value->jumlah_titik * $value->parameterPengujian->biaya,
                    ];
                });
            });

            $dataPengujian = [];
            $i = 0;
            $grandTotal = 0;
            foreach ($_dataPengujian as $key => $value) {
                $dataPengujian[$i]['group'] = $key;
                $dataPengujian[$i]['parameter'] = $value->toArray();
                $dataPengujian[$i]['total'] = $value->sum('total');

                $grandTotal += $dataPengujian[$i]['total'];

                $i++;
            }

            $biayaTambahan = [];

            if ( $pengajuanPengujian->biayaTambahan->count() > 0 ) {
                $biayaTambahan['daftar_biaya'] = $pengajuanPengujian->biayaTambahan->map(function($value){
                    $value['total'] = $value->biaya * $value->jumlah * $value->jumlah_orang;
                    return $value;
                });

                $biayaTambahan['total'] = $biayaTambahan['daftar_biaya']->sum('total');
                $grandTotal += $biayaTambahan['total'];
            }

            $data = [];
            $data['data_pemohon'] = [
                    'regId' => $pengajuanPengujian->regId,
                    'nama_pemohon' => $pengajuanPengujian->nama_pemohon,
                    'alamat' => $pengajuanPengujian->alamat,
                    'email' => $pengajuanPengujian->email,
                    'lokasi_pengajuan' => $pengajuanPengujian->lokasi_pengajuan,
                    'nama_peusahaan' => $pengajuanPengujian->nama_perusahaan,
                    'nomor_telepon' => $pengajuanPengujian->nomor_telepon,
                    'jenis_usaha' => $pengajuanPengujian->jenis_usaha,
                    'tujuan' => $pengajuanPengujian->tujuan_pengujian
            ];
            $data['data_pengujian'] = $dataPengujian;
            $data['biaya_tambahan'] = $biayaTambahan;
            $data['grand_total'] = $grandTotal;

            return $data;
        }

        throw new PengajuanNotFoundException('get one');
    }

    public static function trackingPengajuan($regId = null)
    {
        $pengajuanPengujian = new self($regId);

        if( $pengajuanPengujian->masterPengajuanPengujian instanceof Builder ) {

            $prosesPengajuan = $pengajuanPengujian->prosesPengajuan->map(function($value){
                return [
                    'regId' => $value->pengajuanPengujian->regId,
                    'tahap_pengajuan' => $value->tahapPengajuan->nama,
                    'pic' => $value->tahapPengajuan->user->nama_lengkap,
                    'avatar' => userAvatar($value->tahapPengajuan->user->avatar),
                    'tanggal_mulai' => prettyDate($value->tanggal_mulai),
                    'tanggal_selesai' => prettyDate($value->tanggal_selesai),
                ];
            });

            return dtcApiResponse(200, $prosesPengajuan);
        }

        // return dtcApiResponse(404, null, 'Pengajuan tidak ditemukan');
        throw new PengajuanNotFoundException();
    }

    public function storeBiayaTambahan($data)
    {
        if (count($this->biayaTambahan) == 0) {
            $_biayaTambahan = [];
            foreach ($data['rincian_biaya'] as $key => $value) {
                $_biayaTambahan[$key]['nama_biaya'] = $value;
                $_biayaTambahan[$key]['biaya'] = $data['biaya'][$key];
                $_biayaTambahan[$key]['jumlah'] = $data['jumlah_hari'][$key];
                $_biayaTambahan[$key]['jumlah_orang'] = $data['jumlah_orang'][$key];
            }
            $_biayaTambahan = $this->masterPengajuanPengujian->first()->biayaTambahan()->createMany($_biayaTambahan);
            return $_biayaTambahan;
        }
    }

    public function updateDataPemohon($data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            $pengajuan = $this->masterPengajuanPengujian->first();
            $fillable = $data->only($pengajuan->getFillable());
            $pengajuan->fill($fillable);

            return $pengajuan->save();
        }

        throw new PengajuanNotFoundException();
    }

    public function updateDetail($data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            $this->masterPengajuanPengujian->first()->detailPengajuanPengujian()->delete();
            $detailPengajuanPengujian = [];
            $i = 0;
            foreach ($data->id_parameter_pengujian as $key => $value) {
                $detailPengajuanPengujian[$i]['id_parameter_pengujian'] = $value;
                $detailPengajuanPengujian[$i]['jumlah_titik'] = $data->jumlah_titik[$i];
                $i++;
            }
            return $this->masterPengajuanPengujian->first()->detailPengajuanPengujian()->createMany($detailPengajuanPengujian);
        }
        throw new PengajuanNotFoundException();
    }

    public function updateBiayaTambahan($data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            $this->masterPengajuanPengujian->first()->biayaTambahan()->delete();
            $_biayaTambahan = [];
            foreach ($data['rincian_biaya'] as $key => $value) {
                $_biayaTambahan[$key]['nama_biaya'] = $value;
                $_biayaTambahan[$key]['biaya'] = $data['biaya'][$key];
                $_biayaTambahan[$key]['jumlah'] = $data['jumlah_hari'][$key];
                $_biayaTambahan[$key]['jumlah_orang'] = $data['jumlah_orang'][$key];
            }
            $_biayaTambahan = $this->masterPengajuanPengujian->first()->biayaTambahan()->createMany($_biayaTambahan);

            return $_biayaTambahan;
        }
        throw new PengajuanNotFoundException();
    }

    public function verifikasi($tahap)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            $pengajuan = $this->masterPengajuanPengujian->first();
            $pengajuan->tahap_pengajuan = $tahap;
            $pengajuan->save;

            return true;
        }

        throw new PengajuanNotFoundException();
    }

}
