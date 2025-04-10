<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GovermentResourcesResourcesList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
   public function toArray(Request $request): array
    {
        // Get all fields from the original resource
        $data = parent::toArray($request);

        // Modify only the 'logo' field
        $data['logo'] = $this->modifyLogo($data['logo']);
        $data['about'] = str_replace('&nbsp;', ' ', html_entity_decode(strip_tags($data['about'])));
     
        return $data;
    }

    /**
     * Modify the logo column.
     *
     * @param string $logo
     * @return string
     */
    protected function modifyLogo(string $logo): string
    {
        // Example modification: prepend a URL path to the logo
        return url('public/storage/' . $logo);
    }
}
