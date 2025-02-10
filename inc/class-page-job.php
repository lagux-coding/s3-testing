<?php
class S3Testing_Page_Jobs
{
    public static function page()
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(sprintf(__('%s &rsaquo; Jobs'), S3Testing::get_plugin_data('name')));

    }
}