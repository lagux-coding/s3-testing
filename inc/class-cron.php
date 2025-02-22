<?php

class S3Testing_Cron
{
    public static function run($arg = 'restart')
    {
        if (!is_main_site(get_current_blog_id())) {
            return;
        }

        if($arg === 'restart') {
            //reschedule restart
            wp_schedule_single_event(time() + 300, 's3testing_cron', ['arg' => 'restart']);

            //restart job if not working or a restart imitated
            self::cron_active(['run' => 'restart']);

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
        $cron_next = self::cron_next(S3testing_Option::get($arg, 'cron'));
        wp_schedule_single_event($cron_next, 's3testing_cron', ['arg' => $arg]);

        $log_file = WP_CONTENT_DIR . '/debug' .'/runcron.log';
        $message = 'debug cron run: ' . print_r('Running at ', true). gmdate('Y-m-d H:i:s');
        file_put_contents($log_file, $message . "\n", FILE_APPEND);

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

        if ($args['run'] === 'restart') {
            $job_object = S3Testing_Job::get_working_data();
        }

        S3Testing_Job::start_http($args['run'], $args['jobid']);
    }

    public static function cron_next($cronstring)
    {
        $cronstr = [];
        $cron = [];
        $cronarray = [];

        [
            $cronstr['minutes'],
            $cronstr['hours'],
            $cronstr['day'],
            $cronstr['month'],
            $cronstr['weekday']
        ] = explode(' ', trim($cronstring), 5);

       foreach($cronstr as $key => $value) {
           if(strstr($value, ',')) {
               $cronarr[$key] = explode(',', $value);
           } else {
                $cronarr[$key] = [0 => $value];
           }
       }

       foreach($cronarr as $cronarrkey => $cronarrvalue) {
           $cron[$cronarrkey] = [];
           foreach($cronarrvalue as $value) {
               $step = 1;
                if(strstr($value, '/')) {
                     [$value, $step] = explode('/', $value, 2);
                }

                if($value === '*') {
                    $range = [];
                    if($cronarrkey === 'minutes') {
                        if ($step < 5) { //set step minimum to 5 min.
                            $step = 5;
                        }

                        for($i = 0; $i <= 59; $i = $i + $step) {
                            $range[] = $i;
                        }
                    }
                    if ($cronarrkey === 'hours') {
                        for ($i = 0; $i <= 23; $i = $i + $step) {
                            $range[] = $i;
                        }
                    }
                    if ($cronarrkey === 'day') {
                        for ($i = $step; $i <= 31; $i = $i + $step) {
                            $range[] = $i;
                        }
                    }
                    if ($cronarrkey === 'month') {
                        for ($i = $step; $i <= 12; $i = $i + $step) {
                            $range[] = $i;
                        }
                    }
                    if ($cronarrkey === 'weekday') {
                        for ($i = 0; $i <= 6; $i = $i + $step) {
                            $range[] = $i;
                        }
                    }
                    $cron[$cronarrkey] = array_merge($cron[$cronarrkey], $range);
                }
           }
       }

        //generate years
        $year = (int) gmdate('Y');
        for ($i = $year; $i < $year + 100; ++$i) {
            $cron['year'][] = $i;
        }

        $current_timestamp = (int) current_time('timestamp');

        foreach ($cron['year'] as $year) {
            foreach ($cron['month'] as $month) {
                foreach ($cron['day'] as $day) {
                    foreach ($cron['hours'] as $hours) {
                        foreach ($cron['minutes'] as $minutes) {
                            $timestamp = gmmktime($hours, $minutes, 0, $month, $day, $year);
                            if ($timestamp && in_array(
                                    (int) gmdate('j', $timestamp),
                                    $cron['day'],
                                    true
                                ) && in_array(
                                    (int) gmdate('w', $timestamp),
                                    $cron['day'],
                                    true
                                ) && $timestamp > $current_timestamp) {
                                return $timestamp - ((int) get_option('gmt_offset') * 3600);
                            }
                        }
                    }
                }
            }
        }
            return PHP_INT_MAX;
    }
}