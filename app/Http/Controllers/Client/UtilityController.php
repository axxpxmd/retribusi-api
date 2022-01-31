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
    public function getJenisPendapatan(Request $request)
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

            $opd_id = $user->opd_id;
            $jenis_pendapatans = OPDJenisPendapatan::getJenisPendapatanByOpd($opd_id);

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully',
                'datas'   => $jenis_pendapatans
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function getRincianPendapatan(Request $request, $jenis_pendapatan_id)
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

            $rincian_pendapatans = RincianJenisPendapatan::where('id_jenis_pendapatan', $jenis_pendapatan_id)->get();

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully',
                'datas'   => $rincian_pendapatans
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function getPenandaTangan(Request $request)
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

            $opd_id = $user->opd_id;
            $penanda_tangans = TtdOPD::where('id_opd', $opd_id)->get();
            foreach ($penanda_tangans as $key => $i) {
                $datas[$key] = [
                    'nama' => $i->userDetail->full_name,
                    'nip' => $i->userDetail->nip
                ];
            }

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

    public function getKecamatan(Request $request)
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

            $kecamatans = Kecamatan::select('id', 'n_kecamatan')->where('kabupaten_id', 40)->get();

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully',
                'datas'   => $kecamatans
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function getKelurahan(Request $request, $kecamatan_id)
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

            $kelurahans = Kelurahan::where('kecamatan_id', $kecamatan_id)->get();

            return response()->json([
                'status'  => 200,
                'message' => 'Succesfully',
                'datas'   => $kelurahans
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
