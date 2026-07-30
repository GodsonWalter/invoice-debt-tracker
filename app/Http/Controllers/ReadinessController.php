<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->select('select 1');

            return response()->json(['status' => 'ready']);
        } catch (\Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }
    }
}
