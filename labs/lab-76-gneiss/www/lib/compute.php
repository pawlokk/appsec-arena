<?php

function computeField($s, $returnvalue = 0, $hideerrors = 1, $onlysimplestring = '1')
{
    if ($onlysimplestring == '1') {
        if (preg_match('/[^a-z0-9\s'.preg_quote('^$_+-.*>&|=!?():"\',/@', '/').']/i', $s)) {
            if ($returnvalue) {
                return 'Bad string syntax to evaluate (found chars that are not chars for simplestring): '.$s;
            } else {
                return '';
            }
        }
    } elseif ($onlysimplestring == '2') {
        if (preg_match('/[^a-z0-9\s'.preg_quote('^$_+-.*>&|=!?():"\',/@;[]', '/').']/i', $s)) {
            if ($returnvalue) {
                return 'Bad string syntax to evaluate (found chars that are not chars for simplestring): '.$s;
            } else {
                return '';
            }
        }
    }

    if (is_array($s) || $s === 'Array') {
        return 'Bad string syntax to evaluate (value is Array) '.var_export($s, true);
    }
    if (strpos($s, '::') !== false) {
        if ($returnvalue) {
            return 'Bad string syntax to evaluate (double : char is forbidden): '.$s;
        } else {
            return '';
        }
    }
    if (strpos($s, '`') !== false) {
        if ($returnvalue) {
            return 'Bad string syntax to evaluate (backtick char is forbidden): '.$s;
        } else {
            return '';
        }
    }
    if (preg_match('/[^0-9]+\.[^0-9]+/', $s)) {
        if ($returnvalue) {
            return 'Bad string syntax to evaluate (dot char is forbidden): '.$s;
        } else {
            return '';
        }
    }

    $forbiddenphpstrings = array('$$');
    $forbiddenphpstrings = array_merge($forbiddenphpstrings, array('_ENV', '_SESSION', '_COOKIE', '_GET', '_POST', '_REQUEST'));

    $forbiddenphpfunctions = array("exec", "passthru", "shell_exec", "system", "proc_open", "popen", "eval", "computeField", "executeCLI", "verifCond", "base64_decode");
    $forbiddenphpfunctions = array_merge($forbiddenphpfunctions, array("fopen", "file_put_contents", "fputs", "fputscsv", "fwrite", "fpassthru", "require", "include", "mkdir", "rmdir", "symlink", "touch", "unlink", "umask"));
    $forbiddenphpfunctions = array_merge($forbiddenphpfunctions, array("function", "call_user_func"));

    $forbiddenphpregex = 'global\s+\$|\b('.implode('|', $forbiddenphpfunctions).')\b';

    do {
        $oldstringtoclean = $s;
        $s = str_ireplace($forbiddenphpstrings, '__forbiddenstring__', $s);
        $s = preg_replace('/'.$forbiddenphpregex.'/i', '__forbiddenstring__', $s);
    } while ($oldstringtoclean != $s);

    if (strpos($s, '__forbiddenstring__') !== false) {
        if ($returnvalue) {
            return 'Bad string syntax to evaluate (forbidden keyword): '.$s;
        } else {
            return '';
        }
    }

    if ($returnvalue) {
        if ($hideerrors) {
            return @eval('return '.$s.';');
        } else {
            return eval('return '.$s.';');
        }
    } else {
        if ($hideerrors) {
            @eval($s);
        } else {
            eval($s);
        }
    }
}
