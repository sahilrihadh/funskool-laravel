<?php

namespace App\Http\Resources;

use App\Models\ProductMarketplaceLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductMarketplaceLink */
class ProductMarketplaceLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'url' => $this->url,
        ];
    }
}
