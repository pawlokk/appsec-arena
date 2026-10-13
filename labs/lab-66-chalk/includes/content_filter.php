<?php

function filter_content(string $v): string
{
    $v = strip_tags($v);
    $v = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $v);
    $v = str_replace(["'", '"', ';', '--', '/*', '*/'], '', $v);
    return $v;
}

function sanitise_input(string $v): string
{
    $b = [';', '&&', '||', '|', '$(', '`', "\n", "\r"];
    foreach ($b as $k) {
        $v = str_replace($k, '', $v);
    }
    return $v;
}
