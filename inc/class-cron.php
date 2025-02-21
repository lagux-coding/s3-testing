<?php

class S3Testing_Cron
{
    public static function run($arg = '')
    {
        if (!is_main_site(get_current_blog_id())) {
            return;
        }

        if($arg === 'restart') {
            //reschedule restart
            wp_schedule_single_event(time() + 300, 's3testing_cron', ['arg' => 'restart']);

            //restart job if not working or a restart imitated

            return;
        }

        $arg = is_numeric($arg) ? abs((int) $arg) : 0;
        if (!$arg) {
            return;
        }

        //check that job exits
        $jobids = S3Testing_Option::get_job_ids('activetype', 'wpcron');
        if (!in_array($arg, $jobids, true)) {
            return;
        }

        //delay 5 minutes if job is already running
        $job_object = S3Testing_Job::get_working_data();
        if ($job_object) {
            wp_schedule_single_event(time() + 300, 's3testing_cron', ['arg' => $arg]);

            return;
        }

        //reschedule next job

        //start job
    }

    public static function check_cleanup()
    {

    }

    public static function cron_active($args = [])
    {
        if (!defined('DOING_CRON') || !DOING_CRON) {
            return;
        }

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
                'runnow', 'restart', 'cronrun'
            ],
            true
        )) {
            return;
        };

        $nonce = substr(wp_hash(wp_nonce_tick() . 'backwpup_job_run-' . $args['run'], 'nonce'), -12, 10);
        if ($args['run'] === 'cronrun') {
            $nonce = '';
        }

        S3Testing_Job::start_http($args['run'], $args['jobid']);
    }
}