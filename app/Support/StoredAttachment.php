<?php

namespace App\Support;

final readonly class StoredAttachment
{
    public function __construct(
        public string $path,
        public string $originalName,
        public string $mimeType,
        public int $size,
    ) {
    }
}