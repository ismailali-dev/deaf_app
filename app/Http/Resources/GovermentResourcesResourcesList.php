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
    $data = parent::toArray($request);

    $data['logo']  = $this->modifyLogo($data['logo'] ?? null, $data['title'] ?? '');
    $data['about'] = $this->cleanAbout($data['about'] ?? null);

    return $data;
}

protected function modifyLogo(?string $logo, string $title = ''): string
{
    try {
        if (empty(trim((string) $logo))) {
            return $this->getInitials($title);
        }

        return url('public/storage/' . ltrim($logo, '/'));

    } catch (\Throwable $e) {
        return $this->getInitials($title);
    }
}

protected function getInitials(string $title): string
{
    try {
        $title = trim((string) $title);

        if (empty($title)) {
            return 'N/A';
        }

        // Remove anything in brackets like (ODHH)
        $cleaned = preg_replace('/\(.*?\)/', '', $title);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned); // remove extra spaces

        $words     = array_filter(explode(' ', trim($cleaned)));
        $skipWords = ['for', 'the', 'of', 'and', '&', 'a', 'an', 'to', 'in', 'at', 'by'];

        $initials = '';
        foreach ($words as $word) {
            $word = trim($word);
            if (!empty($word) && !in_array(strtolower($word), $skipWords)) {
                $initials .= strtoupper($word[0]);
            }
        }

        return !empty($initials) ? substr($initials, 0, 3) : 'N/A';

    } catch (\Throwable $e) {
        return 'N/A';
    }
}

protected function cleanAbout(?string $about): string
{
    try {
        if (empty(trim((string) $about))) {
            return '';
        }

        return str_replace('&nbsp;', ' ', html_entity_decode(trim($about)));

    } catch (\Throwable $e) {
        return '';
    }
}


}
