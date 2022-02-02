<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of welcome
 *
 * @author Asip Hamdi
 * Github : axxpxmd
 */

namespace App\Http\Controllers\Client;

use DateTime;
use Validator;
use Carbon\Carbon;

use Illuminate\Http\Request;

use App\Http\Services\VABJB;
use App\Libraries\GenerateNumber;
use App\Http\Controllers\Controller;
use App\Models\DataWP;
use App\Models\RincianJenisPendapatan;
// Models
use App\Models\UserDetail;
use App\Models\TransaksiOPD;
use App\Models\TtdOPD;

class SKRDController extends Controller
{
    public function __construct(VABJB $vabjb)
    {
        $this->vabjb = $vabjb;
    }

    public function index(Request $request)
    {
        $api_key = $request->header('API-Key');
        if (!$api_key) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid API Key!'
            ], 401);
        }

        try {
            $user = UserDetail::where('api_key', $api_key)->first();
            if (!$user) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'User tidak ditemukan!'
                ], 403);
            }

            //* Params
            $end   = $request->end;
            $start = $request->start;
            $length = $request->length;
            $opd_id = $user->opd_id;
            $no_skrd    = $request->no_skrd;
            $status_ttd = $request->status_ttd;

            $datas = TransaksiOPD::querySKRD($length, $opd_id, $no_skrd, $status_ttd, $start, $end);

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully',
                'datas'   => $datas
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function getDiffDays($tgl_skrd_akhir)
    {
        $timeNow = Carbon::now();

        $dateTimeNow = new DateTime($timeNow);
        $expired     = new DateTime($tgl_skrd_akhir . ' 23:59:59');
        $interval    = $dateTimeNow->diff($expired);
        $daysDiff    = $interval->format('%r%a');

        return $daysDiff;
    }

    public function store(Request $request)
    {
        $api_key = $request->header('API-Key');
        if (!$api_key) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid API Key!'
            ], 401);
        }

        $this->validate($request, [
            'id_opd'  => 'required',
            'tgl_ttd' => 'required',
            'penanda_tangan_id' => 'required',
            'alamat_wp'      => 'required',
            'nmr_daftar'     => 'required|unique:tmtransaksi_opd,nmr_daftar',
            'kecamatan_id'   => 'required',
            'kelurahan_id'   => 'required',
            'nm_wajib_pajak' => 'required',
            'tgl_skrd_awal'  => 'required|date_format:Y-m-d',
            'jumlah_bayar'   => 'required',
            'uraian_retribusi'    => 'required',
            'id_jenis_pendapatan' => 'required',
            'id_rincian_jenis_pendapatan' => 'required'
        ]);

        /* Tahapan : 
         * 1. Generate Nomor (no_skrd & no_bayar)
         * 2. Create Virtual Account
         * 3. tmtransaksi_opd
         * 4. tmdata_wp
         */

        try {
            $user = UserDetail::where('api_key', $api_key)->first();
            if (!$user) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'User tidak ditemukan!'
                ], 403);
            }

            //* Tahap 1
            $jenisGenerate = 'no_skrd';
            $no_skrd = GenerateNumber::generate($request->id_opd, $request->id_jenis_pendapatan, $jenisGenerate);

            $jenisGenerate = 'no_bayar';
            $no_bayar = GenerateNumber::generate($request->id_opd, $request->id_jenis_pendapatan, $jenisGenerate);

            //TODO: Check Duplikat (no_bayar, no_skrd)
            $checkGenerate = [
                'no_skrd'  => $no_skrd,
                'no_bayar' => $no_bayar
            ];

            $validator = Validator::make($checkGenerate, [
                'no_skrd'  => 'required|unique:tmtransaksi_opd,no_skrd',
                'no_bayar' => 'required|unique:tmtransaksi_opd,no_bayar',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 500,
                    'message' => 'Terjadi kegagalan saat membuat No SKRD, Silahkan laporkan masalah ini pada administrator'
                ], 500);
            }

            //* Tahap 2
            $tgl_skrd_awal  = Carbon::createFromFormat('Y-m-d',  $request->tgl_skrd_awal);
            $tgl_skrd_akhir = $tgl_skrd_awal->addDays(30)->format('Y-m-d');
            $daysDiff       = $this->getDiffDays($tgl_skrd_akhir);

            //TODO: Check Expired Date (jika tgl_skrd_akhir kurang dari tanggal sekarang maka VA tidak terbuat)
            $VABJB   = '';
            if ($daysDiff > 0) {
                $rincian_pendapatan = RincianJenisPendapatan::find($request->id_rincian_jenis_pendapatan);

                $clientRefnum = $no_bayar;
                $amount       = \strval((int) str_replace(['.', 'Rp', ' '], '', $request->jumlah_bayar));
                $expiredDate  = $tgl_skrd_akhir . ' 23:59:59';
                $customerName = $request->nm_wajib_pajak;
                $productCode  = $rincian_pendapatan->kd_jenis;

                //TODO: Get Token BJB
                $resGetTokenBJB = $this->vabjb->getTokenBJB();
                if ($resGetTokenBJB->successful()) {
                    $resJson = $resGetTokenBJB->json();
                    if ($resJson['rc'] != 0000)
                        return response()->json([
                            'message' => 'Terjadi kegagalan saat mengambil token. Error Code : ' . $resJson['rc'] . '. Message : ' . $resJson['message'] . ''
                        ], 422);
                    $tokenBJB = $resJson['data'];
                } else {
                    return response()->json([
                        'message' => "Terjadi kegagalan saat mengambil token. Error Code " . $resGetTokenBJB->getStatusCode() . ". Silahkan laporkan masalah ini pada administrator"
                    ], 422);
                }

                //TODO: Create VA BJB
                $resGetVABJB = $this->vabjb->createVABJB($tokenBJB, $clientRefnum, $amount, $expiredDate, $customerName, $productCode);
                if ($resGetVABJB->successful()) {
                    $resJson = $resGetVABJB->json();
                    if (isset($resJson['rc']) != 0000)
                        return response()->json([
                            'message' => 'Terjadi kegagalan saat membuat Virtual Account. Error Code : ' . $resJson['rc'] . '. Message : ' . $resJson['message'] . ''
                        ], 422);
                    $VABJB = $resJson['va_number'];
                } else {
                    return response()->json([
                        'message' => "Terjadi kegagalan saat membuat Virtual Account. Error Code " . $resGetVABJB->getStatusCode() . ". Silahkan laporkan masalah ini pada administrator"
                    ], 422);
                }
            }

            //* Tahap 3
            $penanda_tangan = TtdOPD::where('id', $request->penanda_tangan_id)->first();
            $rincian_jenis_pendapatan = RincianJenisPendapatan::find($request->id_rincian_jenis_pendapatan);

            $data = [
                'id_opd'  => $request->id_opd,
                'tgl_ttd' => $request->tgl_ttd,
                'nm_ttd'  => $penanda_tangan->userDetail->full_name,
                'nip_ttd' => $penanda_tangan->userDetail->nip,
                'id_jenis_pendapatan'      => $request->id_jenis_pendapatan,
                'rincian_jenis_pendapatan' => $rincian_jenis_pendapatan->rincian_pendapatan,
                'id_rincian_jenis_pendapatan' => $request->id_rincian_jenis_pendapatan,
                'nmr_daftar'       => $request->nmr_daftar,
                'nm_wajib_pajak'   => $request->nm_wajib_pajak,
                'alamat_wp'        => $request->alamat_wp,
                'lokasi'           => $request->lokasi,
                'kelurahan_id'     => $request->kelurahan_id,
                'kecamatan_id'     => $request->kecamatan_id,
                'uraian_retribusi' => $request->uraian_retribusi,
                'jumlah_bayar'     => (int) str_replace(['.', 'Rp', ' '], '', $request->jumlah_bayar),
                'denda'            => 0,
                'diskon'           => 0,
                'total_bayar'      => (int) str_replace(['.', 'Rp', ' '], '', $request->jumlah_bayar),
                'nomor_va_bjb'     => $VABJB,
                'status_bayar'     => 0,
                'status_denda'     => 0,
                'status_diskon'    => 0,
                'status_ttd'       => 0,
                'no_skrd'          => $no_skrd,
                'tgl_skrd_awal'    => $request->tgl_skrd_awal,
                'tgl_skrd_akhir'   => $tgl_skrd_akhir,
                'no_bayar'         => $no_bayar,
                'created_by'       => $request->created_by
            ];
            TransaksiOPD::create($data);

            //* Tahap 4
            $data = [
                'id_opd'  => $request->id_opd,
                'id_jenis_pendapatan'         => $request->id_jenis_pendapatan,
                'id_rincian_jenis_pendapatan' => $request->id_rincian_jenis_pendapatan,
                'nm_wajib_pajak'   => $request->nm_wajib_pajak,
                'alamat_wp'        => $request->alamat_wp,
                'lokasi'           => $request->lokasi,
                'kelurahan_id'     => $request->kelurahan_id,
                'kecamatan_id'     => $request->kecamatan_id
            ];

            $where = [
                'id_opd' => $request->id_opd,
                'nm_wajib_pajak' => $request->nm_wajib_pajak,
                'id_jenis_pendapatan' => $request->id_jenis_pendapatan,
                'id_rincian_jenis_pendapatan' => $request->id_rincian_jenis_pendapatan,
            ];

            //TODO: Check existed data wajib Retribusi (menyimpan data wp jika belum pernah dibuat)
            $check = DataWP::where($where)->count();
            if ($check == 0)
                DataWP::create($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
