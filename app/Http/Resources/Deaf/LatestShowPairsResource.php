<?php

namespace App\Http\Resources\Deaf;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LatestShowPairsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'latest_sentences_pairs' => SentenceResourceList::collection($this->sentences),
            'latest_words_pairs' => WordResourceList::collection($this->words),
        ];
    }
}
