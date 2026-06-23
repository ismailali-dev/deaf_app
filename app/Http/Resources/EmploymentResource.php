<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmploymentResource extends JsonResource
{
    /**
     * Define the mapping of employment types.
     *
     * @var array<string, string>
     */
    protected $employmentTypes = [
        "0" => "Full Time",
        "1" => "Part Time",
        "2" => "Internship"
    ];

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get all fields from the original resource
        $data = parent::toArray($request);

        // Modify the 'employe_type' field based on the status mapping
        // $data['employe_type'] = $this->employmentTypes[$data['employe_type']] ?? 'Unknown';
        
        // $data['short_description'] = str_replace('&nbsp;', ' ', html_entity_decode(strip_tags($data['short_description'])));
        
        // $data['long_description'] = str_replace('&nbsp;', ' ', html_entity_decode(strip_tags($data['long_description'])));
        
        
        // Modify the 'employe_type' field based on the status mapping
        @$data['employe_type'] = $this->employmentTypes[@$data['employe_type']] ?? '';
        
        @$data['short_description'] = str_replace('&nbsp;', ' ', html_entity_decode(strip_tags(@$data['short_description'])));
        
        @$data['long_description'] = str_replace('&nbsp;', ' ', html_entity_decode(strip_tags(@$data['long_description'])));

        return $data;
    }
}
