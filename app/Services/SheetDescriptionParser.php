<?php

namespace App\Services;

class SheetDescriptionParser
{
    /**
     * Parse description Stock Sheet menjadi gramature & dimension.
     *
     * Contoh: "BK350 590X840" -> gramature = "BK350", dimension = "590X840"
     * Contoh 3 kata: "GB1350 640 800" -> gramature = "GB1350", dimension = "800"
     * (kata terakhir selalu dimension, kata pertama selalu gramature; kata di
     * tengah, jika ada, diabaikan karena tidak diminta oleh spesifikasi).
     *
     * @param string $description
     * @return array|null Null jika description kosong atau kurang dari 2 kata
     */
    public function parse(string $description): ?array
    {
        $description = trim($description);

        if ($description === '') {
            return null;
        }

        $words = preg_split('/\s+/', $description);

        if (count($words) < 2) {
            return null;
        }

        $gramature = $words[0];
        $dimension = $words[count($words) - 1];

        if ($gramature === '' || $dimension === '') {
            return null;
        }

        return [
            'gramature' => $gramature,
            'dimension' => $dimension,
            'description_raw' => $description,
        ];
    }
}
