<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $leads = Lead::where('is_active', true)
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);

        return response()->json($leads);
    }
}