<?php

namespace App\Services;

use Mews\Purifier\Facades\Purifier;

class RichTextSanitizer
{
    /**
     * Sanitize rich (Tiptap/HTML) content, dropping anything outside the
     * allowed whitelist configured in config/purifier.php under "tiptap".
     */
    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        return Purifier::clean($html, 'tiptap');
    }
}
