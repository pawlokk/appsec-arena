<?php
function verify_credential(string $stored, mixed $submitted): bool
{
    if (!is_string($submitted)) {
        return false;
    }
    return $stored === $submitted;
}

function verify_api_key(string $expected, mixed $received): bool
{
    if (!is_string($received)) {
        return false;
    }
    return hash_equals($expected, $received);
}
