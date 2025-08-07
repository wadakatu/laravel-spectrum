<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use LaravelSpectrum\Contracts\HasCustomExamples;

class ProductResource extends JsonResource implements HasCustomExamples
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'status' => $this->status,
            'tags' => $this->tags,
            'created_at' => $this->created_at,
        ];
    }

    public static function getExampleMapping(): array
    {
        return [
            'name' => fn ($faker) => $faker->words(3, true).' '.$faker->randomElement(['Pro', 'Plus', 'Max']),
            'price' => fn ($faker) => $faker->randomFloat(2, 99.99, 999.99),
            'currency' => fn ($faker) => $faker->randomElement(['USD', 'EUR', 'JPY']),
            'status' => fn ($faker) => $faker->randomElement(['in_stock', 'out_of_stock', 'discontinued']),
            'tags' => fn ($faker) => $faker->words(3),
        ];
    }
}
