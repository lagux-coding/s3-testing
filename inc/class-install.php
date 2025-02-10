<?php
class S3Testing_Install
{
    public static function activate()
    {
        if(get_site_option('s3testing_jobs')){
            return;
        }

        if (is_multisite()) {
            add_site_option('s3testing_jobs', []);
        } else {
            add_option('s3testing_jobs', [], null, 'no');
        }

        S3Testing_Option::default_site_options();
    }
}