<?php

namespace Tests\Unit;

use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use RuntimeException;

it('throws a clear error when the uploaded file cannot be written to disk', function () {
    $file = $this->createMock(UploadedFile::class);
    $file->expects($this->once())->method('store')->willReturn(false);

    (new MediaService)->store($file);
})->throws(RuntimeException::class);
