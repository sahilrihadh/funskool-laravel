<?php

namespace App\Http\Resources\Channel;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Product */
class ChannelProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Absolute URLs — the consumer is usually another domain.
        $url = fn (?string $path) => $path ? (str_starts_with($path, 'http') ? $path : url(Storage::url($path))) : null;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'slug' => $this->slug,
            'name' => $this->name,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'regular_price' => (float) $this->regular_price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'price' => $this->price,
            'stock_quantity' => (int) $this->stock_quantity,
            'in_stock' => $this->stock_quantity > 0,
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['slug' => $this->category->slug, 'name' => $this->category->name] : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? ['slug' => $this->brand->slug, 'name' => $this->brand->name] : null),
            'image' => $url($this->image),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => $url($image->image_path))),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'attributes' => $variant->getAttribute('attributes'),
                'price' => $variant->effective_price,
                'stock_quantity' => (int) $variant->stock_quantity,
                'is_active' => (bool) $variant->is_active,
            ])),
            'seo' => [
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
            ],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
