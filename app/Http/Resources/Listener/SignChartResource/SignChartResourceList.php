<?php

namespace App\Http\Resources\Listener\SignChartResource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignChartResourceList extends JsonResource
{
    /**
     * Define the mapping of sign types.
     *
     * @var array<string, string>
     */
    protected $sign_types = [
        "1" => 'Alphabet Signs',
        "2" => 'Number Signs',
        "3" => 'Road Signs',
        "4" => 'Frequent Used Words Signs',
    ];

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get all fields from the original resource
        $data = parent::toArray($request);

        // Modify the 'sign_type' field based on the sign_types mapping
        $data['sign_type'] = $this->sign_types[$this->sign_type] ?? 'Unknown';

        return $data;
    }
}
