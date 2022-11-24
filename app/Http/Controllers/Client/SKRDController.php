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
use App\Traits\ResponseAction;
use App\Libraries\GenerateNumber;
use App\Http\Controllers\Controller;

// Models
use App\Models\TtdOPD;
use App\Models\DataWP;
use App\Models\UserDetail;
use App\Models\TransaksiOPD;
use App\Models\RincianJenisPendapatan;

class SKRDController extends Controller
{
    use ResponseAction;

    public function __construct(VABJB $vabjb)
    {
        $this->vabjb = $vabjb;
    }

    public function index(Request $request)
    {
        //* Check Api Key
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return $this->failure('Invalid API Key!', 422);
        }

        try {
            //* Params
            $end    = $request->end;
            $start  = $request->start;
            $length = $request->length;
            $opd_id = $user->opd_id;
            $no_skrd    = $request->no_skrd;
            $status_ttd = $request->status_ttd;

            $skrds = TransaksiOPD::querySKRD($length, $opd_id, $no_skrd, $status_ttd, $start, $end);

            return response()->json([
                'status'  => 200,
                'message' => 'Success',
                'data'    => $skrds,
            ], 200);
        } catch (\Throwable $th) {
            return $this->failure('Server Error.', 500);
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
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid API Key!'
            ], 401);
        }
        $id_opd  = $user->opd_id;

        $this->validate($request, [
            'tgl_ttd' => 'required',
            'id_penanda_tangan' => 'required',
            'alamat_wr'      => 'required|max:150',
            'nmr_daftar'     => 'required|unique:tmtransaksi_opd,nmr_daftar|max:30',
            'id_kecamatan'   => 'required',
            'id_kelurahan'   => 'required',
            'nama_wr' => 'required|max:100',
            'tgl_skrd_awal'  => 'required|date_format:Y-m-d',
            'jumlah_bayar'   => 'required',
            'uraian_retribusi'    => 'required|max:300',
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
            //* Tahap 1
            $jenisGenerate = 'no_skrd';
            $no_skrd = GenerateNumber::generate($id_opd, $request->id_jenis_pendapatan, $jenisGenerate);

            $jenisGenerate = 'no_bayar';
            $no_bayar = GenerateNumber::generate($id_opd, $request->id_jenis_pendapatan, $jenisGenerate);

            //TODO: Check Duplikat (no_bayar, no_skrd)
            $customAttributes = [
                'no_skrd'  => $no_skrd,
                'no_bayar' => $no_bayar
            ];

            $validator = Validator::make($customAttributes, [
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
                $customerName = $request->nama_wr;
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
            $penanda_tangan = TtdOPD::where('id', $request->id_penanda_tangan)->first();
            $rincian_jenis_pendapatan = RincianJenisPendapatan::find($request->id_rincian_jenis_pendapatan);

            $data = [
                'id_opd'  => $id_opd,
                'tgl_ttd' => $request->tgl_ttd,
                'nm_ttd'  => $penanda_tangan->userDetail->full_name,
                'nip_ttd' => $penanda_tangan->userDetail->nip,
                'id_jenis_pendapatan'      => $request->id_jenis_pendapatan,
                'rincian_jenis_pendapatan' => $rincian_jenis_pendapatan->rincian_pendapatan,
                'id_rincian_jenis_pendapatan' => $request->id_rincian_jenis_pendapatan,
                'nmr_daftar'       => $request->nmr_daftar,
                'nm_wajib_pajak'   => $request->nama_wr,
                'alamat_wp'        => $request->alamat_wr,
                'lokasi'           => $request->lokasi,
                'kelurahan_id'     => $request->id_kelurahan,
                'kecamatan_id'     => $request->id_kecamatan,
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
                'created_by'       => $request->created_by . ' | ' . 'API Retribusi',
                'c_status'         => 1,
                'user_api'         => $user->id
            ];
            $skrd = TransaksiOPD::create($data);
            $skrdResponse = [
                'jenis_pendapatan' => $skrd->jenis_pendapatan->jenis_pendapatan,
                'rincian_jenis_pendapatan' => $skrd->rincian_jenis_pendapatan,
                'kecamatan' => $skrd->kecamatan->n_kecamatan,
                'kelurahan' => $skrd->kelurahan->n_kelurahan,
                'nm_ttd' => $skrd->nm_ttd,
                'nip_ttd' => $skrd->nip_ttd,
                'nmr_daftar' => $skrd->nmr_daftar,
                'nama_wr' => $skrd->nm_wajib_pajak,
                'alamat_wr' => $skrd->alamat_wp,
                'lokasi' => $skrd->lokasi,
                'uraian_retribusi' => $skrd->uraian_retribusi,
                'tgl_skrd_awal' => $skrd->tgl_skrd_awal,
                'tgl_ttd' => $skrd->tgl_ttd,
                'jumlah_bayar' => $skrd->jumlah_bayar,
                'nomor_va_bjb' => $skrd->nomor_va_bjb,
                'status_bayar' => $skrd->status_bayar,
                'status_ttd' => $skrd->status_ttd,
                'no_skrd' => $skrd->no_skrd,
                'no_bayar' => $skrd->no_bayar,
                'created_by' => $skrd->created_by
            ];

            //* Tahap 4
            $data = [
                'id_opd'  => $id_opd,
                'id_jenis_pendapatan'         => $request->id_jenis_pendapatan,
                'id_rincian_jenis_pendapatan' => $request->id_rincian_jenis_pendapatan,
                'nm_wajib_pajak'   => $request->nama_wr,
                'alamat_wp'        => $request->alamat_wr,
                'lokasi'           => $request->lokasi,
                'kelurahan_id'     => $request->id_kelurahan,
                'kecamatan_id'     => $request->id_kecamatan
            ];

            $where = [
                'id_opd' => $id_opd,
                'nm_wajib_pajak' => $request->nama_wr,
                'id_jenis_pendapatan' => $request->id_jenis_pendapatan,
                'id_rincian_jenis_pendapatan' => $request->id_rincian_jenis_pendapatan,
            ];

            //TODO: Check existed data wajib Retribusi (menyimpan data wp jika belum pernah dibuat)
            $check = DataWP::where($where)->count();
            if ($check == 0)
                DataWP::create($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Success',
                'data' => $skrdResponse
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid API Key!'
            ], 401);
        }

        try {
            $data = TransaksiOPD::find($id);

            if ($user->opd_id != $data->id_opd) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Akses dibatasi, data ini dari OPD lain.'
                ], 403);
            }

            $dataResponse = [
                'n_opd' => $data->opd->n_opd,
                'jenis_pendapatan' => $data->jenis_pendapatan->jenis_pendapatan,
                'rincian_jenis_pendapatan' => $data->rincian_jenis_pendapatan,
                'uraian_retribusi' => $data->uraian_retribusi,
                'nmr_rekening' => $data->rincian_jenis->nmr_rekening,
                'nmr_rekening_denda' => $data->rincian_jenis->nmr_rekening_denda,
                'nama_ttd' => $data->nm_ttd,
                'nip_ttd' => $data->nip_ttd,
                'tgl_ttd' => $data->tgl_ttd,
                'nmr_daftar' => $data->nmr_daftar,
                'nama_wr' => $data->nm_wajib_pajak,
                'alamat_wr' => $data->alamat_wp,
                'kecamatan' => $data->kecamatan->n_kecamatan,
                'kelurahan' => $data->kelurahan->n_kelurahan,
                'lokasi' => $data->lokasi,
                'tgl_skrd' => $data->tgl_skrd_awal,
                'jatuh_tempo' => $data->tgl_skrd_akhir,
                'no_skrd' => $data->no_skrd,
                'no_bayar' => $data->no_bayar,
                'ketetapan' => $data->jumlah_bayar,
                'denda' => $data->denda,
                'diskon' => $data->diskon,
                'total_bayar' => $data->total_bayar,
                'nomor_va_bjb' => $data->nomor_va_bjb,
                'status_ttd' => $data->status_ttd,
                'text_qris' => $data->text_qris,
                'invoice_id' => $data->invoice_id,
                'created_by' => $data->created_by
            ];

            return response()->json([
                'status'  => 200,
                'message' => 'Success',
                'data'   => $dataResponse
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid API Key!'
            ], 401);
        }

        $data = TransaksiOPD::find($id);

        if ($data->status_bayar == 1) {
            return response()->json([
                'status'  => 403,
                'message' => 'Tidak bisa edit, Data sudah dibayar.'
            ], 403);
        }

        $this->validate($request, [
            'nama_wr' => 'required|max:100',
            'tgl_ttd' => 'required',
            'alamat_wr'  => 'required|max:150',
            'nmr_daftar' => 'required|unique:tmtransaksi_opd,nmr_daftar|max:30',
            'jumlah_bayar'  => 'required',
            'tgl_skrd_awal' => 'required|date_format:Y-m-d',
            'id_penanda_tangan' => 'required',
            'uraian_retribusi'  => 'required|max:300',
            'id_rincian_jenis_pendapatan' => 'required',
        ]);

        /* Tahapan : 
         * 1. Update VA BJB / Create VA BJB
         * 2. tmtransaksi_opd
         */

        try {
            //* Tahap 1
            $amount = \strval((int) str_replace(['.', 'Rp', ' '], '', $request->jumlah_bayar));
            $expiredDate  = $request->tgl_skrd_akhir . ' 23:59:59';
            $customerName = $request->nm_wajib_pajak;
            $va_number    = (int) $data->nomor_va_bjb;
            $VABJB        = $data->nomor_va_bjb;
            $clientRefnum = $data->no_bayar;
            $productCode  = $request->kd_jenis;
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function showNoBayar($no_bayar)
    {
        try {
            $data = TransaksiOPD::whereno_bayar($no_bayar)->first();

            //* Check Data
            if ($data == null)
                return response()->json([
                    'status'  => 404,
                    'message' => 'Error, Data nomor bayar tidak ditemukan.',
                ], 404);

            $dataResponse = [
                'n_opd' => $data->opd->n_opd,
                'jenis_pendapatan' => $data->jenis_pendapatan->jenis_pendapatan,
                'rincian_jenis_pendapatan' => $data->rincian_jenis_pendapatan,
                'uraian_retribusi' => $data->uraian_retribusi,
                'nmr_rekening' => $data->rincian_jenis->nmr_rekening,
                'nmr_rekening_denda' => $data->rincian_jenis->nmr_rekening_denda,
                'nama_ttd' => $data->nm_ttd,
                'nip_ttd' => $data->nip_ttd,
                'tgl_ttd' => $data->tgl_ttd,
                'nmr_daftar' => $data->nmr_daftar,
                'nama_wr' => $data->nm_wajib_pajak,
                'alamat_wr' => $data->alamat_wp,
                'kecamatan' => $data->kecamatan->n_kecamatan,
                'kelurahan' => $data->kelurahan->n_kelurahan,
                'lokasi' => $data->lokasi,
                'tgl_skrd' => $data->tgl_skrd_awal,
                'jatuh_tempo' => $data->tgl_skrd_akhir,
                'no_skrd' => $data->no_skrd,
                'no_bayar' => $data->no_bayar,
                'ketetapan' => $data->jumlah_bayar,
                'denda' => $data->denda,
                'diskon' => $data->diskon,
                'total_bayar' => $data->total_bayar,
                'nomor_va_bjb' => $data->nomor_va_bjb,
                'status_ttd' => $data->status_ttd,
                'text_qris' => $data->text_qris,
                'status_bayar' => $data->status_bayar,
                'invoice_id' => $data->invoice_id,
                'created_by' => $data->created_by
            ];

            return response()->json([
                'status'  => 200,
                'message' => 'Success',
                'data'   => $dataResponse
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function showPDFSTS($no_bayar)
    {
        try {
            $data = TransaksiOPD::whereno_bayar($no_bayar)->first();

            //* Check Data
            if ($data == null)
                return response()->json([
                    'status'  => 404,
                    'message' => 'Error, Data nomor bayar tidak ditemukan.',
                ], 404);

            //* Check TTD
            if ($data->status_ttd == 2 || $data->status_ttd == 4)
                return response()->json([
                    'status'  => 404,
                    'message' => 'File SKRD belum ditanda tangan.',
                ], 404);

            //* Check status bayar
            if ($data->status_bayar == 1)
                return response()->json([
                    'status'  => 404,
                    'message' => 'SKRD belum dibayar.',
                ], 404);

            $fileName = str_replace(' ', '', $data->nm_wajib_pajak) . '-' . $data->no_skrd . ".pdf";
            $link = 'https://dataawan.tangerangselatankota.go.id/retribusi/file_ttd_skrd/' . $fileName;

            return response()->json([
                'status'  => 200,
                'message' => 'Success',
                'data'   => $link
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
