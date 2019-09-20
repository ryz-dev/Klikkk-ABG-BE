<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Http\Resources\PengajuanPengujian as PengajuanPengujianResource;
use Illuminate\Database\Eloquent\Builder;
use App\Exceptions\PengajuanNotFoundException;
use App\Exceptions\ProsesDoubleException;
use App\Exports\BerkasKup;
use App\Repositories\Traits\UploadTrait;
use Maatwebsite\Excel\Facades\Excel;

class PengajuanPengujian
{
    use UploadTrait;

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

    public static function getPeraturanPengujian($data)
    {
        $pengajuanPengujian = (new self);
        $jenisPengujian = $pengajuanPengujian->jenisPengujian->active()->whereIn('id', $data->jenis_pengujian)->with('peraturanParameter' )->get();

        // dd($jenisPengujian);

        return dtcApiResponse(200,$jenisPengujian);
    }

    public static function getParameterPengujian($data, $regId = null)
    {

        if ($regId) {
            $pengajuanPengujian = (new self($regId));
            $jenisPengujian = $pengajuanPengujian->jenisPengujian->active()->whereIn('id', $data->jenis_pengujian)->with(['parameterPengujian' => function($query){
                return $query->active();
            }])->get();

            $parameterSebelumnya = $pengajuanPengujian->detailPengajuanPengujian;

            $jenisPengujian->map(function($valueJenis) use ($parameterSebelumnya){
                $valueJenis->parameterPengujian->transform(function($value) use ($parameterSebelumnya){
                    $value->selected = $parameterSebelumnya->contains('id_parameter_pengujian', $value->id);
                    if ($value->selected) {
                        $value->jumlah_titik = $parameterSebelumnya->where('id_parameter_pengujian', $value->id)->first()->jumlah_titik;
                    }
                    return $value;
                });

            });
        }
        else{
            $pengajuanPengujian = (new self);
            $jenisPengujian = $pengajuanPengujian->jenisPengujian->active()->whereIn('id', $data->jenis_pengujian)->with(['parameterPengujian' => function($query){
                return $query->active();
            }])->get();
        }


        return dtcApiResponse(200, $jenisPengujian);
    }

    public function store($data, $status = 'aktif')
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

        DB::transaction(function() use($masterPengajuanPengujian, $detailPengajuanPengujian, $prosesPengajuan, $status) {
            $masterPengajuanPengujian->status_pengajuan = $status;
            $masterPengajuanPengujian->save();

            $masterPengajuanPengujian->detailPengajuanPengujian()->createmany($detailPengajuanPengujian);

            $masterPengajuanPengujian->prosesPengajuan()->save($prosesPengajuan);


        });

        return $this::getOne($masterPengajuanPengujian->regId);

    }

    public function storeDraft($data)
    {

        if ($this->masterPengajuanPengujian instanceof Builder) {
            $masterPengajuanPengujian = $this->masterPengajuanPengujian->first();
            $prosesPengajuan = $this->prosesPengajuan;

            $_masterPengajuan = $data->only($masterPengajuanPengujian->getFillable());

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
                $masterPengajuanPengujian->status_pengajuan = 'aktif';
                $masterPengajuanPengujian->save();

                $masterPengajuanPengujian->detailPengajuanPengujian()->createmany($detailPengajuanPengujian);

                $masterPengajuanPengujian->prosesPengajuan()->save($prosesPengajuan->first());


            });

            return $this::getOne($masterPengajuanPengujian->regId);
        }

        throw new PengajuanNotFoundException();

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
                'nama_perusahaan' => $value->nama_perusahaan,
                'rencana_lokasi_pengujian' => $value->rencana_lokasi_pengujian,
                'avatar' => userAvatar(isset($value->users->avatar) ? $value->users->avatar:null)
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
                        'id_jenis_pengujian' => $value->parameterPengujian->jenisPengujian->id,

                    ];
                });
            });

            $dataPengujian = [];
            $i = 0;
            $totalBiayaPengujian = 0;
            $diskon = 0;
            $grandTotal = 0;

            foreach ($_dataPengujian as $key => $value) {
                $dataPengujian[$i]['id_group'] = $value->first()['id_jenis_pengujian'];
                $dataPengujian[$i]['group'] = $key;
                $dataPengujian[$i]['parameter'] = $value->toArray();
                $dataPengujian[$i]['total'] = $value->sum('total');
                $totalBiayaPengujian += $dataPengujian[$i]['total'];

                $i++;
            }


            $biayaTambahan = null;

            if ( $pengajuanPengujian->biayaTambahan->count() > 0 ) {
                $biayaTambahan['daftar_biaya'] = $pengajuanPengujian->biayaTambahan->map(function($value){
                    $value['total'] = $value->biaya * $value->jumlah * $value->jumlah_orang;
                    return $value;
                });

                $biayaTambahan['total'] = $biayaTambahan['daftar_biaya']->sum('total');
                $grandTotal += $biayaTambahan['total'];
            }

            $data = [];
            $nilai_diskon = $totalBiayaPengujian * ($pengajuanPengujian->discount / 100);
            $data['data_pemohon'] = [
                    'regId' => $pengajuanPengujian->regId,
                    'nama_pemohon' => $pengajuanPengujian->nama_pemohon,
                    'alamat' => $pengajuanPengujian->alamat,
                    'email' => $pengajuanPengujian->email,
                    'rencana_lokasi_pengujian' => $pengajuanPengujian->rencana_lokasi_pengujian,
                    'nama_perusahaan' => $pengajuanPengujian->nama_perusahaan,
                    'nomor_telepon' => $pengajuanPengujian->no_telepon,
                    'jenis_usaha' => $pengajuanPengujian->jenis_usaha,
                    'tujuan' => $pengajuanPengujian->tujuan_pengujian,
                    'e_billing' => buktiTransaksiPengajuan($pengajuanPengujian->e_billing),
                    'bukti_transaksi' => buktiTransaksiPengajuan($pengajuanPengujian->bukti_transaksi),
                    'berkas_kup' => buktiTransaksiPengajuan($pengajuanPengujian->berkas_kup),
                    'berkas_proposal' => buktiTransaksiPengajuan($pengajuanPengujian->berkas_proposal),
                    'berkas_surat_pengantar' => buktiTransaksiPengajuan($pengajuanPengujian->berkas_surat_pengantar),
                    'komentar' => $pengajuanPengujian->keterangan,
                    'discount' => $pengajuanPengujian->discount ?? 0,
                    'created_at' => $pengajuanPengujian->created_at,
                    'nilai_diskon' => $nilai_diskon,
                    'total_biaya_pengujian_setelah_diskon' => $pengajuanPengujian->discount ? $totalBiayaPengujian - $nilai_diskon : $totalBiayaPengujian
            ];

            $data['data_pengujian'] = $dataPengujian;
            $data['total_biaya_pengujian'] = $totalBiayaPengujian;
            $data['biaya_tambahan'] = $biayaTambahan;
            $data['grand_total'] = $grandTotal + $data['data_pemohon']['total_biaya_pengujian_setelah_diskon'];

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
                    'pekerjaan' => $value->tahapPengajuan->user->pekerjaan,
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

            if ($data->has('discount')) {
                $masterPengajuan = $this->masterPengajuanPengujian->first();
                $masterPengajuan->discount = $data->discount??0;
                // dd($masterPengajuan);
                $masterPengajuan->save();
            }

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
            // cek proses sudah ada
            $proses = $pengajuan->prosesPengajuan->sortByDesc('tanggal_mulai');
            if ($proses = $proses->first()) {
                if($proses->tahap_pengajuan == $tahap){
                    throw new ProsesDoubleException();
                }
            }

            $pengajuan->tahap_pengajuan = $tahap;
            $pengajuan->save;

            return true;
        }

        throw new PengajuanNotFoundException();
    }

    public function updateEbilling($data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            $pengajuan = $this->masterPengajuanPengujian->first();
            $pengajuan->e_billing = $data->e_billing;
            $pengajuan->save();
            return true;
        }

        throw new PengajuanNotFoundException();
    }

    public function uploadBuktiTransaksi($data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            try {
                if ($data->has('bukti_transaksi')) {
                    $pengajuan = $this->masterPengajuanPengujian->first();
                    $image = $data->file('bukti_transaksi');
                    $name = str_slug($data->input('nama_lengkap')).'_'.time();
                    $folder = '/uploads/bukti_transaksi/';
                    $filePath = $folder . $name. '.' . $image->getClientOriginalExtension();
                    $this->uploadOne($image, $folder, 'public', $name);
                    $pengajuan->bukti_transaksi = $filePath;

                    $pengajuan->save();
                    return $pengajuan->bukti_transaksi;
                }
            } catch (\Exception $e) {
                return dtcApiResponse(500,false, $e->getMessage());
            }
        }
        else{
            throw new PengajuanNotFoundException();
        }
    }

    public function uploadKup($data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            try {
                if ($data->has('kup')) {
                    $pengajuan = $this->masterPengajuanPengujian->first();
                    $image = $data->file('kup');
                    $name = $pengajuan->regId;
                    $folder = '/uploads/kup/';
                    $filePath = $folder . $name. '.' . $image->getClientOriginalExtension();
                    $this->uploadOne($image, $folder, 'public', $name);
                    $pengajuan->berkas_kup = $filePath;

                    $pengajuan->save();
                    return asset('storage'.$pengajuan->berkas_kup);
                }
            } catch (\Exception $e) {
                return dtcApiResponse(500,false, $e->getMessage());
            }
        }
        else{
            throw new PengajuanNotFoundException();
        }
    }

    public function uploadProposal($data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            try {
                if ($data->has('proposal')) {
                    $pengajuan = $this->masterPengajuanPengujian->first();
                    $image = $data->file('proposal');
                    $name = $pengajuan->regId;
                    $folder = '/uploads/proposal/';
                    $filePath = $folder . $name. '.' . $image->getClientOriginalExtension();
                    $this->uploadOne($image, $folder, 'public', $name);
                    $pengajuan->berkas_proposal = $filePath;

                    $pengajuan->save();
                    return asset('storage'.$pengajuan->berkas_proposal);
                }
            } catch (\Exception $e) {
                return dtcApiResponse(500,false, $e->getMessage());
            }
        }
        else{
            throw new PengajuanNotFoundException();
        }
    }

    public function uploadSuratPEngantar($data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            try {
                if ($data->has('surat-pengantar')) {
                    $pengajuan = $this->masterPengajuanPengujian->first();
                    $image = $data->file('surat-pengantar');
                    $name = $pengajuan->regId;
                    $folder = '/uploads/surat_pengantar/';
                    $filePath = $folder . $name. '.' . $image->getClientOriginalExtension();
                    $this->uploadOne($image, $folder, 'public', $name);
                    $pengajuan->berkas_surat_pengantar = $filePath;

                    $pengajuan->save();
                    return asset('storage'.$pengajuan->berkas_surat_pengantar);
                }
            } catch (\Exception $e) {
                return dtcApiResponse(500,false, $e->getMessage());
            }
        }
        else{
            throw new PengajuanNotFoundException();
        }
    }

    public function uploadEbilling($data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            try {
                if ($data->has('ebilling')) {
                    $pengajuan = $this->masterPengajuanPengujian->first();
                    $image = $data->file('ebilling');
                    $name = $pengajuan->regId;
                    $folder = '/uploads/ebilling/';
                    $filePath = $folder . $name. '.' . $image->getClientOriginalExtension();
                    $this->uploadOne($image, $folder, 'public', $name);
                    $pengajuan->e_billing = $filePath;

                    $pengajuan->save();
                    return asset('storage'.$pengajuan->e_billing);
                }
            } catch (\Exception $e) {
                return dtcApiResponse(500,false, $e->getMessage());
            }
        }
        else{
            throw new PengajuanNotFoundException();
        }
    }

    public function cetak()
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            $pengajuan = $this->getOne($this->masterPengajuanPengujian->first()->regId);
            $groupPengujian = [];

            $kepalaBidang = \App\Models\User::whereHas('roles' ,function($q){
                $q->where('name', 'kepala_bagian');
            });

            $kepalaBidang = $kepalaBidang->first();

            foreach ($pengajuan['data_pengujian'] as $key => $value) {
                $_groupPengujian = explode('-',$value['group']);
                if (collect($groupPengujian)->has($_groupPengujian[0]) == false) {
                    $groupPengujian[] = $_groupPengujian[0];
                }
            }

            $groupPengujian = array_unique($groupPengujian);

            $bulan = namaBulan(date('m', strtotime($pengajuan['data_pemohon']['created_at'])));
            $tahun = date('Y', strtotime($pengajuan['data_pemohon']['created_at']));


            $pdf = \PDF::loadView('berkas.proposal',compact('pengajuan','groupPengujian', 'bulan', 'tahun', 'kepalaBidang'));
            $pdf->save('storage/uploads/berkas/_'.$pengajuan['data_pemohon']['regId'].'.pdf');

            return asset('storage/uploads/berkas/_'.$pengajuan['data_pemohon']['regId'].'.pdf');
        }
        throw new PengajuanNotFoundException();
    }

    public function downloadTemplateKup()
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            $pengajuan = $this->getOne($this->masterPengajuanPengujian->first()->regId);
            $groupPengujian = [];

            foreach ($pengajuan['data_pengujian'] as $key => $value) {
                $_groupPengujian = explode('-',$value['group']);
                if (collect($groupPengujian)->has($_groupPengujian[0]) == false) {
                    $groupPengujian[] = $_groupPengujian[0];
                }
            }

            $groupPengujian = array_unique($groupPengujian);

            Excel::store(new BerkasKup($pengajuan), $pengajuan['data_pemohon']['regId'].'.xlsx','public');

            return asset('storage/'.$pengajuan['data_pemohon']['regId'].'.xlsx');
        }

        throw new PengajuanNotFoundException();
    }

    public function uploadBerkas($tipe,$data)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            try {
                if ($data->has('bukti_transaksi')) {
                    $pengajuan = $this->masterPengajuanPengujian->first();
                    $image = $data->file('bukti_transaksi');
                    $name = str_slug($data->input('nama_lengkap')).'_'.time();
                    $folder = '/uploads/bukti_transaksi/';
                    $filePath = $folder . $name. '.' . $image->getClientOriginalExtension();
                    $this->uploadOne($image, $folder, 'public', $name);
                    $pengajuan->bukti_transaksi = $filePath;

                    $pengajuan->save();
                    return $pengajuan->bukti_transaksi;
                }
            } catch (\Exception $e) {
                return dtcApiResponse(500,false, $e->getMessage());
            }
        }
        else{
            throw new PengajuanNotFoundException();
        }
    }

    public function historyUser()
    {
        $pengajuan = $this->masterPengajuanPengujian;
        $pengajuan = $pengajuan->history()->orderBy('created_at','DESC')->get()->groupBy('status_pengajuan')->map(function($value, $key){

            return $value->map(function($value) use ($key){

                $data['nomor_pengajuan'] = $value->regId;
                $data['nama_pemohon'] = $value->nama_pemohon;
                $data['tanggal_pengajuan'] = prettyDate($value->created_at);
                $data['tujuan_pengujian'] = $value->tujuan_pengujian;
                $data['avatar'] = userAvatar($value->users->avatar);
                $data['status_pengajuan'] = $value->status_pengajuan;
                $data['path'] = $key=="draft"?'pengajuan/pengujian/draft/'.$value->regId:'pengajuan/pengujian/view/'.$value->regId;

                return $data;
            });
        });

        // dd($pengajuan);

        $aktif = isset($pengajuan['aktif'])?$pengajuan['aktif']->toArray():[];
        $tidakAktif = isset($pengajuan['tidak aktif'])?$pengajuan['tidak aktif']->toArray():[];
        $draft = isset($pengajuan['draft'])?$pengajuan['draft']->toArray():null;
        $tolak = isset($pengajuan['tolak'])?$pengajuan['tolak']->toArray():[];
        $selesai = isset($pengajuan['selesai'])?$pengajuan['selesai']->toArray():[];

        $daftarPengajuan = collect(array_merge($aktif,$tidakAktif,$tolak,$selesai))->sortByDesc('tanggal_pengajuan');
        $daftarDraft = collect($draft)->sortByDesc('tanggal_pengajuan');

        return [
            'riwayat' => $daftarPengajuan,
            'draft' => $daftarDraft
        ];
    }

    public function tolak($komentar)
    {
        if ($this->masterPengajuanPengujian instanceof Builder) {
            $pengajuan = $this->masterPengajuanPengujian->first();
            $pengajuan->status_pengajuan = 'tolak';
            $pengajuan->keterangan = $komentar;

            $pengajuan->save();
            return true;
        }
        throw new PengajuanNotFoundException();
    }

    public static function riwayat($tahap, $request)
    {
        $pengajuanPengujian = new \App\Models\PengajuanPengujian();

        $pengajuanPengujian = $pengajuanPengujian->where('tahap_pengajuan','>',$tahap)->latest()->paginate(10);
        $pengajuanPengujian->getCollection()->transform(function($value){
            return [
                'nomor_pengajuan' => $value->regId,
                'nama_pemohon' => $value->nama_pemohon,
                'tanggal_pengajuan' => prettyDate($value->created_at),
                'tujuan_pengujian' => $value->tujuan_pengujian,
                'nama_perusahaan' => $value->nama_perusahaan,
                'rencana_lokasi_pengujian' => $value->rencana_lokasi_pengujian,
                'avatar' => userAvatar($value->users->avatar)
            ];

        });

        return $pengajuanPengujian;

    }

    public static function statistik($year)
    {
        $pengajuanPengujian = (new self);
        $namaBulan =  [1=>'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $masterPengajuan = $pengajuanPengujian->masterPengajuanPengujian;
        $statistik = $masterPengajuan->whereYear('created_at', $year)->get()->groupBy(function($value){
            return substr($value->created_at,5,2);
        });

        $rawYears = \DB::table('pengajuan_pengujian')
                ->select(\DB::raw('SUBSTRING(created_at,1,4) AS YEARS'))
                ->groupBy(\DB::raw('SUBSTRING(created_at,1,4)'))->get();

        $years = [];
        foreach ($rawYears as $key => $value) {
            $years[$key] = $value->YEARS;
        }

        $dataStatistik = [];
        $totalPengajuanMasuk = 0;
        $i = 1;
        foreach ($statistik as $key => $value) {
            $dataStatistik['detail'][] = [

                    'name' => $namaBulan[(int)$key],
                    'pengajuan_masuk' => $value->count(),
                    'pengajuan_terproses' => $pengajuanPengujian->pengajuanTerproses($year,$key),
                    'pengajuan_tidak_terproses' => $pengajuanPengujian->pengajuanTidakTerproses($year,$key)

            ];
            $totalPengajuanMasuk+= (int) $value->count();
            $i++;
        }

        $dataStatistik['master'] = [
            'pengajuan_masuk' => $totalPengajuanMasuk,
            'pengajuan_terproses' => $pengajuanPengujian->pengajuanTerproses($year),
            'pengajuan_tidak_terproses' => $pengajuanPengujian->pengajuanTidakTerproses($year)
        ];

        $dataStatistik['years'] = $years;

        return array_values([$dataStatistik]);
    }

    private function pengajuanTerproses($y, $m = null){
        $prosesPengajuan = $this->prosesPengajuan;
        $prosesPengajuan = $prosesPengajuan->where('tahap_pengajuan',19)->whereYear('tanggal_selesai',$y);
        $prosesPengajuan = $m ? $prosesPengajuan->whereMonth('tanggal_selesai', (int)$m) : $prosesPengajuan;
        return $prosesPengajuan->get()->count();
    }

    private function pengajuanTidakTerproses($y, $m = null){
        $masterPengujian = $this->masterPengajuanPengujian;
        $masterPengujian = $masterPengujian->where('status_pengajuan','tolak')->whereYear('updated_at',$y);
        $masterPengujian = $m ? $masterPengujian->whereMonth('updated_at', (int)$m) : $masterPengujian;

        return $masterPengujian->get()->count();
    }

}
