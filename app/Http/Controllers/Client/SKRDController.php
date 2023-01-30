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

use Validator;
use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Traits\ResponseAction;
use App\Http\Services\VABJBRes;
use App\Libraries\GenerateNumber;
use App\Http\Services\QRISBJBRes;
use App\Http\Controllers\Controller;

// Models
use App\Models\TtdOPD;
use App\Models\Utility;
use App\Models\UserDetail;
use App\Models\TransaksiOPD;
use App\Models\RincianJenisPendapatan;

class SKRDController extends Controller
{
    use ResponseAction;

    public function __construct(VABJBRes $vabjbres, QRISBJBRes $qrisbjbres)
    {
        $this->vabjbres = $vabjbres;
        $this->qrisbjbres = $qrisbjbres;
    }

    public function index(Request $request)
    {
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();

        //* Check Api Key
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
                'message' => 'Sukses',
                'data'    => $skrds,
            ], 200);
        } catch (\Throwable $th) {
            return $this->failure('Server Error.', 500);
        }
    }

    public static function generateNumber($id_opd, $id_jenis_pendapatan)
    {
        $jenisGenerate = 'no_skrd';
        $no_skrd = GenerateNumber::generate($id_opd, $id_jenis_pendapatan, $jenisGenerate);

        $jenisGenerate = 'no_bayar';
        $no_bayar = GenerateNumber::generate($id_opd, $id_jenis_pendapatan, $jenisGenerate);

        //TODO: Check Duplikat (no_bayar, no_skrd)
        $checkGenerate = [
            'no_skrd'  => $no_skrd,
            'no_bayar' => $no_bayar
        ];

        $validator = Validator::make($checkGenerate, [
            'no_skrd'  => 'required|unique:tmtransaksi_opd,no_skrd',
            'no_bayar' => 'required|unique:tmtransaksi_opd,no_bayar',
        ]);

        $error = false;
        if ($validator->fails()) {
            $error = true;
        }

        return [$no_skrd, $no_bayar, $error];
    }

    public function store(Request $request)
    {
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();

        //* Check Api Key
        if (!$api_key || !$user) {
            return $this->failure('Invalid API Key!', 422);
        }

        $id_opd  = $user->opd_id;
        $this->validate($request, [
            'lokasi'  => 'required',
            'tgl_ttd' => 'required',
            'nama_pemohon'   => 'required',
            'alamat_pemohon' => 'required',
            'nmr_daftar'     => 'required|unique:tmtransaksi_opd,nmr_daftar',
            'id_kecamatan'   => 'required',
            'id_kelurahan'   => 'required',
            'tgl_skrd'  => 'required|date_format:Y-m-d',
            'jumlah_bayar'   => 'required',
            'uraian_retribusi'    => 'required',
            'id_penanda_tangan'   => 'required',
            'id_jenis_pendapatan' => 'required',
            'id_rincian_jenis_pendapatan' => 'required',
        ]);

        /* Tahapan : 
         * 1. Generate Nomor (no_skrd & no_bayar)
         * 2. tmtransaksi_opd 
         * 3. Create Virtual Account
         * 4. Create QRIS
         * 5. tmdata_wp
         */

        //* Check jenis_pendapatan_id
        $opd_id = $user->opd_id;
        $checkJenisPendapatan = RincianJenisPendapatan::checkExistedJenisPendapatan($opd_id, $request->id_jenis_pendapatan);
        if (!$checkJenisPendapatan) {
            $message = 'parameter jenis_pendapatan_id tidak sesuai.';
            return $this->failure($message, 422);
        }

        try {
            //* Tahap 1
            list($no_skrd, $no_bayar, $error) = $this->generateNumber($id_opd, $request->id_jenis_pendapatan);
            if ($error) {
                return response()->json([
                    'status'  => 500,
                    'message' => 'Terjadi kegagalan saat membuat No SKRD, Silahkan laporkan masalah ini pada administrator'
                ], 500);
            }

            //* Tahap 2
            DB::beginTransaction(); //* DB Transaction Begin

            $tgl_skrd_awal  = Carbon::createFromFormat('Y-m-d',  $request->tgl_skrd);
            $tgl_skrd_akhir = $tgl_skrd_awal->addDays(30)->format('Y-m-d');

            $penanda_tangan = TtdOPD::where('id', $request->id_penanda_tangan)->first();
            $rincian_jenis_pendapatan = RincianJenisPendapatan::find($request->id_rincian_jenis_pendapatan);
            $rincian_pendapatan = RincianJenisPendapatan::find($request->id_rincian_jenis_pendapatan);

            $data = [
                'id_opd'  => $id_opd,
                'tgl_ttd' => $request->tgl_ttd,
                'nm_ttd'  => $penanda_tangan->userDetail->full_name,
                'nip_ttd' => $penanda_tangan->userDetail->nip,
                'id_jenis_pendapatan'      => $request->id_jenis_pendapatan,
                'rincian_jenis_pendapatan' => $rincian_jenis_pendapatan->rincian_pendapatan,
                'id_rincian_jenis_pendapatan' => $request->id_rincian_jenis_pendapatan,
                'nmr_daftar'       => $request->nmr_daftar,
                'nm_wajib_pajak'   => $request->nama_pemohon,
                'alamat_wp'        => $request->alamat_pemohon,
                'lokasi'           => $request->lokasi,
                'kelurahan_id'     => $request->id_kelurahan,
                'kecamatan_id'     => $request->id_kecamatan,
                'uraian_retribusi' => $request->uraian_retribusi,
                'jumlah_bayar'     => (int) str_replace(['.', 'Rp', ' '], '', $request->jumlah_bayar),
                'denda'            => 0,
                'diskon'           => 0,
                'total_bayar'      => (int) str_replace(['.', 'Rp', ' '], '', $request->jumlah_bayar),
                'nomor_va_bjb'     => null,
                'invoice_id'       => null,
                'text_qris'        => null,
                'status_bayar'     => 0,
                'status_denda'     => 0,
                'status_diskon'    => 0,
                'status_ttd'       => 0,
                'no_skrd'          => $no_skrd,
                'tgl_skrd_awal'    => $request->tgl_skrd,
                'tgl_skrd_akhir'   => $tgl_skrd_akhir,
                'no_bayar'         => $no_bayar,
                'created_by'       => $user->full_name . ' | ' . 'API Retribusi',
                'c_status'         => 1,
                'user_api'         => $user->id,
                'email'            => $request->email,
                'no_telp'          => $request->no_telp
            ];
            $dataSKRD = TransaksiOPD::create($data);

            $clientRefnum = $no_bayar;
            $amount       = \strval((int) str_replace(['.', 'Rp', ' '], '', $request->jumlah_bayar));
            $expiredDate  = $tgl_skrd_akhir . ' 23:59:59';;
            $customerName = $request->nama_pemohon;
            $productCode  = $rincian_pendapatan->kd_jenis;
            $no_hp = $rincian_pendapatan->no_hp;

            //*: Check Expired Date (jika tgl_skrd_akhir kurang dari tanggal sekarang maka VA dan QRIS tidak terbuat)
            //*: Check Amount (jika nominal 0 rupiah makan VA dan QRIS tidak terbuat)
            list($dayDiff, $monthDiff) = Utility::getDiffDate($tgl_skrd_akhir);
            if ($dayDiff > 0 && $amount != 0) {
                //* Tahap 3
                //TODO: Get Token VA
                list($err, $errMsg, $tokenBJB) = $this->vabjbres->getTokenBJBres(1);
                if ($err) {
                    DB::rollback(); //* DB Transaction Failed
                    return response()->json([
                        'message' => $errMsg
                    ], 500);
                }

                //TODO: Create VA
                list($err, $errMsg, $VABJB) = $this->vabjbres->createVABJBres($tokenBJB, $clientRefnum, $amount, $expiredDate, $customerName, $productCode, 1, $no_bayar);
                if ($err) {
                    DB::rollback(); //* DB Transaction Failed
                    return response()->json([
                        'message' => $errMsg
                    ], 500);
                } else {
                    //* Update data SKRD
                    $dataSKRD->update([
                        'nomor_va_bjb' => $VABJB
                    ]);
                }

                //* Tahap 4
                if ($amount <= 10000000) { //* Nominal QRIS maksimal 10 juta, jika lebih maka tidak terbuat
                    //TODO: Get Token QRIS
                    list($err, $errMsg, $tokenQRISBJB) = $this->qrisbjbres->getTokenQrisres();
                    if ($err) {
                        DB::rollback(); //* DB Transaction Failed
                        return response()->json([
                            'message' => $errMsg
                        ], 500);
                    }

                    //TODO: Create QRIS
                    list($err, $errMsg, $invoiceId, $textQRIS) = $this->qrisbjbres->createQRISres($tokenQRISBJB, $amount, $no_hp, 1, $no_bayar);
                    if ($err) {
                        DB::rollback(); //* DB Transaction Failed
                        return response()->json([
                            'message' => $errMsg
                        ], 500);
                    } else {
                        //* Update data SKRD
                        $dataSKRD->update([
                            'invoice_id' => $invoiceId,
                            'text_qris' => $textQRIS
                        ]);
                    }
                }
            }

            DB::commit(); //* DB Transaction Success

            //* LOG
            Log::channel('skrd_create')->info('Create Data SKRD', $dataSKRD->toArray());

            $skrdResponse = [
                'jenis_pendapatan' => $dataSKRD->jenis_pendapatan->jenis_pendapatan,
                'rincian_jenis_pendapatan' => $dataSKRD->rincian_jenis_pendapatan,
                'kecamatan' => $dataSKRD->kecamatan->n_kecamatan,
                'kelurahan' => $dataSKRD->kelurahan->n_kelurahan,
                'nm_ttd' => $dataSKRD->nm_ttd,
                'nip_ttd' => $dataSKRD->nip_ttd,
                'nmr_daftar' => $dataSKRD->nmr_daftar,
                'nama_pemohon' => $dataSKRD->nm_wajib_pajak,
                'alamat_pemohon' => $dataSKRD->alamat_wp,
                'lokasi' => $dataSKRD->lokasi,
                'uraian_retribusi' => $dataSKRD->uraian_retribusi,
                'tgl_skrd' => $dataSKRD->tgl_skrd_awal,
                'tgl_jatuh_tempo' => $dataSKRD->tgl_skrd_akhir,
                'tgl_ttd' => $dataSKRD->tgl_ttd,
                'jumlah_bayar' => $dataSKRD->jumlah_bayar,
                'nomor_va_bjb' => $dataSKRD->nomor_va_bjb,
                'invoice_id' => $dataSKRD->invoice_id,
                'text_qris' => $dataSKRD->text_qris,
                'status_bayar' => $dataSKRD->status_bayar,
                'status_ttd' => $dataSKRD->status_ttd,
                'no_skrd' => $dataSKRD->no_skrd,
                'no_bayar' => $dataSKRD->no_bayar,
                'created_by' => $dataSKRD->created_by,
                'email' => $dataSKRD->email,
                'no_telp' => $dataSKRD->no_telp
            ];

            return response()->json([
                'status'  => 200,
                'message' => 'Sukses, SKRD berhasil terbuat!.',
                'data' => $skrdResponse
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback(); //* DB Transaction Failed
            return $this->failure('Server Error.', 500);
        }
    }

    public function show(Request $request, $id)
    {
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();

        //* Check Api Key
        if (!$api_key || !$user) {
            return response()->json([
                'status'  => 401,
                'message' => 'Invalid API Key!'
            ], 401);
        }

        try {
            $data = TransaksiOPD::find($id);
            if (!$data) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Error, Data tidak ditemukan'
                ], 404);
            }

            //* Check data OPD
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
                'nama_pemohon' => $data->nm_wajib_pajak,
                'alamat_pemohon' => $data->alamat_wp,
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
                'invoice_id' => $data->invoice_id,
                'text_qris' => $data->text_qris,
                'status_ttd' => $data->status_ttd,
                'text_qris' => $data->text_qris,
                'invoice_id' => $data->invoice_id,
                'created_by' => $data->created_by,
                'email' => $data->email,
                'no_telp' => $data->no_telp
            ];

            return response()->json([
                'status'  => 200,
                'message' => 'Sukses',
                'data'   => $dataResponse
            ], 200);
        } catch (\Throwable $th) {
            return $this->failure('Server Error.', 500);
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
            if ($data->status_ttd == 2 || $data->status_ttd == 4 || $data->status_ttd == 0)
                return response()->json([
                    'status'  => 404,
                    'message' => 'File SKRD belum ditanda tangan.',
                ], 404);

            //* Check status bayar
            if ($data->status_bayar == 0)
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
