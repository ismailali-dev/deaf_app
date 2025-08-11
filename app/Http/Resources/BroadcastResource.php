<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class BroadcastResource extends JsonResource
{
    public function toArray($request): array
    {
        $remainingTime = '00:00:00';

        if ($this->type === 'specific' && $this->end_time) {
            $endTime = Carbon::parse($this->end_time, config('app.timezone'));
            $now = Carbon::now(config('app.timezone'));

            if ($now->lt($endTime)) {
                $diff = $endTime->diff($now);
                $remainingTime = sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s);
            }
        }

        return [
            'id' => $this->id,
            'role_id' => $this->role_id,
            'user_id' => $this->user_id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'duration' => $this->duration,
            'age_group_from' => $this->age_group_from,
            'age_group_to' => $this->age_group_to,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'user_avatar' => optional($this->user)->avatar,
            'user_name' => optional($this->user)->username ?? '',
            'name' => optional($this->user)->name ?? '',
            'user_email' => optional($this->user)->email ?? '',
            'user_age' => optional($this->user)->date_of_birth
                ? Carbon::parse($this->user->date_of_birth)->age
                : null,

            'isFriend' => (bool) ($this->is_friend ?? false),
            'remaining_time' => $remainingTime,
        ];
    }
}