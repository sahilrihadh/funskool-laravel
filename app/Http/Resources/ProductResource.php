<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'regular_price' => (float) $this->regular_price,
            'sale_price' => $this->sale_price ? (float) $this->sale_price : null,
            'price' => $this->price,
            'image' => $this->image ? Storage::url($this->image) : null,
            'stock_quantity' => $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
            'is_featured' => $this->is_featured,
            'average_rating' => $this->average_rating,
            'reviews_count' => $this->whenCounted('approvedReviews') ?? $this->approvedReviews()->count(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'marketplace_links' => ProductMarketplaceLinkResource::collection($this->whenLoaded('marketplaceLinks')),
            'reviews' => ReviewResource::collection($this->whenLoaded('approvedReviews')),
        ];
    }
}
