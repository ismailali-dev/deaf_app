<?php

namespace App\Http\Resources\Common;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
     
    public function toArray(Request $request): array
    {
       
        // Get all fields from the original resource
        $data = parent::toArray($request);

        // // Check if the 'avatar' key exists before modifying it
        // if (isset($data['avatar'])) {
        //     // Modify only the 'avatar' field if it exists
        //     $data['avatar'] = $this->modifyAvatar($data['avatar']);
        // } else {
        //     // Optionally, you can set a default value or handle the absence of 'avatar'
        //     $data['avatar'] = url('public/storage/default_avatar.png'); // Example default
        // }

        return $data;
    }

    /**
     * Modify the $avatar column.
     *
     * @param string $avatar
     * @return string
     */
    // protected function modifyAvatar(string $avatar): string
    // {
    //     // Example modification: prepend a URL path to the avatar
    //     return url('public/storage/' . $avatar);
    // }
}