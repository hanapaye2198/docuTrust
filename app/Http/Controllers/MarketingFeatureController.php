<?php

namespace App\Http\Controllers;

use App\Support\MarketingFeatures;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MarketingFeatureController extends Controller
{
    public function show(Request $request, string $feature): View
    {
        $record = MarketingFeatures::find($feature);

        abort_if($record === null, 404);

        return view('features.show', [
            'feature' => $record,
            'allFeatures' => MarketingFeatures::all(),
        ]);
    }
}
