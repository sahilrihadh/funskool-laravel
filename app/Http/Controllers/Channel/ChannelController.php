<?php

namespace App\Http\Controllers\Channel;

use App\Http\Controllers\Controller;
use App\Models\SalesChannel;
use Illuminate\Http\Request;

abstract class ChannelController extends Controller
{
    protected function channel(Request $request): SalesChannel
    {
        return $request->attributes->get('sales_channel');
    }
}
