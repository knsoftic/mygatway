<?php

namespace App\Integrations\Support;

use App\Support\Decimal;

/**
 * JSON encoder that writes RawDecimal values as bare JSON numbers without
 * passing them through PHP floats (Binance Pay expects numeric amounts).
 */
final class JsonBody
{
    /** @param  array<string, mixed>  $data */
    public static function encode(array $data): string
    {
        $replacements = [];
        $counter = 0;

        array_walk_recursive($data, function (&$value) use (&$replacements, &$counter) {
            if ($value instanceof RawDecimal) {
                $token = '__RAWDEC_'.($counter++).'__';
                $replacements['"'.$token.'"'] = Decimal::plain($value->value);
                $value = $token;
            }
        });

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return strtr($json, $replacements);
    }
}
