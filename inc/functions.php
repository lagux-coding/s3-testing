<?php
function s3testing_wpdb()
{
    global $wpdb;

    return $wpdb;
}
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

function s3testing_esc_url_default_secure($url, $protocols = null)
{
    // Add https to protocols if not present
    if (is_array($protocols) && !in_array('https', $protocols)) {
        $protocols[] = 'https';
    }

    $escaped_url = esc_url_raw($url, $protocols);
    if (empty($escaped_url)) {
        return $escaped_url;
    }

    // We must check for both http: and http;
    // because esc_url_raw() corrects http; to http: automatically.
    // so if we do not check for it in the original, we could have invalid results.
    if (!preg_match('/http[:;]/', $url) && strpos($escaped_url, 'http://') === 0) {
        $escaped_url = preg_replace('/^http:/', 'https:', $escaped_url);
    }

    return $escaped_url;
}