<?php
function get_param(string $name, $default = ''): mixed
{
    return $_REQUEST[$name] ?? $default;
}

function post_param(string $name, $default = ''): mixed
{
    return $_POST[$name] ?? $default;
}

function query_param(string $name, $default = ''): mixed
{
    return $_GET[$name] ?? $default;
}
