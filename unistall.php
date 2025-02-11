<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

global $wpdb;

if ( ! class_exists( \S3Testing::class ) ) {
    if (is_multisite()) {
        $wpdb->query('DELETE FROM ' . $wpdb->sitemeta . " WHERE meta_key LIKE '%s3testing_%' ");
    } else {
        $wpdb->query('DELETE FROM ' . $wpdb->options . " WHERE option_name LIKE '%s3testing_%' ");
    }
}