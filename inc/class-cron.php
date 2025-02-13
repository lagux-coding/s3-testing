<?php

class S3Testing_Cron
{
    public static function cron_active($args = [])
    {
        $log_file = WP_CONTENT_DIR . '/debug-cron.log';
        $message = 'cron_active called with args: ' . print_r($args, true);
        file_put_contents($log_file, $message . "\n", FILE_APPEND);

        if(!is_array($args)){
            $args = [];
        }

        if(isset($_GET['s3testing_run'])) {
            $args['run'] = sanitize_text_field($_GET['s3testing_run']);
        }

        if (isset($_GET['_nonce'])) {
            $args['nonce'] = sanitize_text_field($_GET['_nonce']);
        }

        if (isset($_GET['jobid'])) {
            $args['jobid'] = absint($_GET['jobid']);
        }

        $args = array_merge(
            [
                'run' => '',
                'nonce' => '',
                'jobid' => 0,
            ],
            $args
        );

        if(!in_array(
            $args['run'],
            [
                'runnow',
            ],
            true
        )) {
            return;
        };

        S3Testing_Job::start_http($args['run'], $args['jobid']);
    }
}