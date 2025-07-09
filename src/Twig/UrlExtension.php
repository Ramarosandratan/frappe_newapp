<?php

namespace App\Twig;

use App\Service\UrlHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class UrlExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('encode_id', [$this, 'encodeId']),
            new TwigFilter('decode_id', [$this, 'decodeId']),
        ];
    }

    public function encodeId(string $id): string
    {
        return UrlHelper::encodeId($id);
    }

    public function decodeId(string $encodedId): string
    {
        return UrlHelper::decodeId($encodedId);
    }
}