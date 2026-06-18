<?php

namespace App\Services;

class DescriptionParser
{
    /**
     * Parse description string into papertype, gramature, playbond, width.
     *
     * @param string $description
     * @return array|null Returns null if parsing fails (less than 4 words or invalid pattern)
     */
    public function parse(string $description): ?array
    {
        // Clean and split
        $description = trim($description);
        $words = preg_split('/\s+/', $description);
        
        // Need at least 4 words for valid parsing
        if (count($words) < 4) {
            return null;
        }
        
        // Parse from the end
        $width = end($words); // last word
        $playbond = prev($words); // second from last
        
        // If playbond is "-", treat as null
        if ($playbond === '-') {
            $playbond = null;
        }
        
        $gramature = prev($words); // third from last
        
        // Papertype = all words before gramature
        reset($words);
        $papertypeWords = [];
        while (current($words) !== $gramature) {
            $papertypeWords[] = current($words);
            next($words);
        }
        $papertype = implode(' ', $papertypeWords);
        
        // Validate that we have all required fields (papertype can't be empty)
        if (empty($papertype) || empty($gramature) || empty($width)) {
            return null;
        }
        
        return [
            'papertype' => $papertype,
            'gramature' => $gramature,
            'playbond' => $playbond,
            'width' => $width,
            'description_raw' => $description,
        ];
    }
    
    /**
     * Validate and parse a batch of descriptions.
     *
     * @param array $descriptions
     * @return array [
     *     'valid' => array of parsed data,
     *     'invalid' => array of invalid descriptions with reasons
     * ]
     */
    public function parseBatch(array $descriptions): array
    {
        $valid = [];
        $invalid = [];
        
        foreach ($descriptions as $index => $description) {
            if (empty(trim($description))) {
                $invalid[] = [
                    'row' => $index + 1,
                    'description' => $description,
                    'reason' => 'Empty description',
                ];
                continue;
            }
            
            $parsed = $this->parse($description);
            
            if ($parsed === null) {
                $invalid[] = [
                    'row' => $index + 1,
                    'description' => $description,
                    'reason' => 'Description has less than 4 words or cannot be parsed',
                ];
            } else {
                $valid[] = $parsed;
            }
        }
        
        return [
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }
}