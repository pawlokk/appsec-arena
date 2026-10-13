<?php
function generate_reset_token(int $user_id): string
{
    return md5(date('His') . $user_id);
}

function verify_reset_token($stored, $submitted): bool
{
    if ($stored === null || $stored === '') {
        return false;
    }
    if (!is_string($submitted) || !preg_match('/^[a-f0-9]{32}$/', $submitted)) {
        return false;
    }
    return $stored == $submitted;
}
