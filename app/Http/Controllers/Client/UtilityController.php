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

use Illuminate\Http\Request;

use App\Helpers\Helper;
use App\Traits\ResponseAction;
use App\Http\Controllers\Controller;

// Models
use App\Models\TtdOPD;
use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\UserDetail;
use App\Models\OPDJenisPendapatan;
use App\Models\RincianJenisPendapatan;

class UtilityController extends Controller
{
    use ResponseAction;

    public function __construct(Helper $helper)
    {
        $this->helper = $helper;
    }

    public function getJenisPendapatan(Request $request)
    {
        //* Check Api Key
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return $this->failure('Invalid API Key!', 422);
        }

        try {
            $opd_id = $user->opd_id;
            $jenis_pendapatans = OPDJenisPendapatan::getJenisPendapatanByOpd($opd_id);

            $datas = [];
            foreach ($jenis_pendapatans as $key => $i) {
                $datas[$key] = [
                    'id' => $i->id,
                    'jenis_pendapatan' => $i->jenis_pendapatan
                ];
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Sukses',
                'data'    => $datas
            ], 200);
        } catch (\Throwable $th) {
            return $this->failure('Server Error.', 500);
        }
    }

    public function getRincianPendapatan(Request $request, $jenis_pendapatan_id)
    {
        //* Check Api Key
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return $this->failure('Invalid API Key!', 422);
        }

        try {
            //* Check jenis_pendapatan_id
            $opd_id = $user->opd_id;
            $checkJenisPendapatan = $this->helper->checkExistedJenisPendapatan($opd_id, $jenis_pendapatan_id);
            if (!$checkJenisPendapatan) {
                $message = 'jenis_pendapatan_id tidak sesuai.';
                return $this->failure($message, 422);
            }

            $rincian_pendapatans = RincianJenisPendapatan::where('id_jenis_pendapatan', $jenis_pendapatan_id)->get();

            $datas = [];
            foreach ($rincian_pendapatans as $key => $i) {
                $datas[$key] = [
                    'id' => $i->id,
                    'kd_jenis' => $i->kd_jenis,
                    'rincian_pendapatan' => $i->rincian_pendapatan,
                    'nmr_rekening' => $i->nmr_rekening,
                    'nmr_rekening_denda' => $i->nmr_rekening_denda
                ];
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Sukses',
                'data'    => $datas
            ], 200);
        } catch (\Throwable $th) {
            return $this->failure('Server Error.', 500);
        }
    }

    public function getPenandaTangan(Request $request)
    {
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return $this->failure('Invalid API Key!', 422);
        }

        try {
            $opd_id = $user->opd_id;
            $penanda_tangans = TtdOPD::where('id_opd', $opd_id)->get();

            $datas = [];
            foreach ($penanda_tangans as $key => $i) {
                $datas[$key] = [
                    'id' => $i->id,
                    'nama' => $i->userDetail->full_name,
                    'nip' => $i->userDetail->nip
                ];
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Sukses',
                'data'    => $datas
            ], 200);
        } catch (\Throwable $th) {
            return $this->failure('Server Error.', 500);
        }
    }

    public function getKecamatan(Request $request)
    {
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return $this->failure('Invalid API Key!', 422);
        }

        try {
            $kecamatans = Kecamatan::select('id', 'n_kecamatan')->where('kabupaten_id', 40)->get();

            return response()->json([
                'status'  => 200,
                'message' => 'Sukses',
                'data'   => $kecamatans
            ], 200);
        } catch (\Throwable $th) {
            return $this->failure('Server Error.', 500);
        }
    }

    public function getKelurahan(Request $request, $kecamatan_id)
    {
        $api_key = $request->header('API-Key');
        $user    = UserDetail::where('api_key', $api_key)->first();
        if (!$api_key || !$user) {
            return $this->failure('Invalid API Key!', 422);
        }

        try {
            $kelurahans = Kelurahan::where('kecamatan_id', $kecamatan_id)->get();

            return response()->json([
                'status'  => 200,
                'message' => 'Sukses',
                'data'   => $kelurahans
            ], 200);
        } catch (\Throwable $th) {
            return $this->failure('Server Error.', 500);
        }
    }
}
