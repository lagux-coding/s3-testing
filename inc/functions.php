<?php

function remove_invalid_characters_from_directory_name($directory)
{
    return str_replace(
        [
            '?',
            '[',
            ']',
            '\\',
            '=',
            '<',
            '>',
            ':',
            ';',
            ',',
            "'",
            '"',
            '&',
            '$',
            '#',
            '*',
            '(',
            ')',
            '|',
            '~',
            '`',
            '!',
            '{',
            '}',
            chr(0),
        ],
        '',
        $directory
    );
}