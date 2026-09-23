<?php

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Files live under businesses/{business_id}/{media_id}/ on every disk (docs/16 "Uploads").
 * The media id keeps the path unique; the business id keeps a company's files together.
 */
class BusinessPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->basePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media).'/responsive/';
    }

    private function basePath(Media $media): string
    {
        return 'businesses/'.$media->model_id.'/'.$media->getKey();
    }
}
