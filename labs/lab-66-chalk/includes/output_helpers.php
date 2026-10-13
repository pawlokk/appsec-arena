<?php

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function eattr(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function eurl(string $v): string
{
    return urlencode($v);
}
