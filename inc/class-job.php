<?php

class S3Testing_Job
{
    public $job = [];
    public $start_time = 0;
    public $temp = [];
    public $backup_folder = '';
    public $backup_file_name = '';

    public static function start_http($starttype, $jobid = 0)
    {
        echo "starttype: $starttype, jobid: $jobid";
    }

    public function get_folders_to_backup()
    {
        $file = S3Testing::get_plugin_data('temp') . 's3testing-' . S3Testing::get_plugin_data('hash') . '-folder.php';

        if(!file_exists($file)) {
            return [];
        }

        $folders = [];

        //return array of each line in file
        $file_data = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach($file_data as $file) {
            $folder = trim(str_replace(['<?php', '//'], '', (string) $folder));
            if (!empty($folder) && is_dir($folder)) {
                $folders[] = $folder;
            }
        }
        $folders = array_unique($folders);
        sort($folders);

        return $folders;
    }

    public function add_folders_to_backup($folders = [], $new = false)
    {
        if (!is_array($folders)) {
            $folders = (array) $folders;
        }

        $file = S3Testing::get_plugin_data('temp') . 's3testing-' . S3Testing::get_plugin_data('hash') . '-folder.php';

        try {
            if (!file_exists($file) || $new) {
                file_put_contents($file, '<?php' . PHP_EOL);
            }
        } catch (Exception $e) {

        }

        $content = '';

        foreach ($folders as $folder) {
            $content .= '//' . $folder . PHP_EOL;
        }

        if ($content) {
            file_put_contents($file, $content, FILE_APPEND);
        }
    }

    public static function get_jobrun_url($starttype, $jobid = 0)
    {
        $url = site_url('wp-cron.php');
        $query_args = [
            '_nonce' => substr(wp_hash(wp_nonce_tick() . 's3testing_job_run-' . $starttype, 'nonce'), -12, 10),
        ];

        if (in_array($starttype, ['runnow'], true)) {
            $query_args['s3testing_run'] = $starttype;
        }

        if (in_array($starttype, ['runnowlink', 'runnow'], true) && !empty($jobid)) {
            $query_args['jobid'] = $jobid;
        }

        if ($starttype === 'runnowlink') {
            $url = wp_nonce_url(network_admin_url('admin.php'), 's3testing_job_run-' . $starttype);
            $query_args['page'] = 's3testingjobs';
            $query_args['action'] = 'runnow';
            unset($query_args['_nonce']);
        }

        $request = [
            'url' => add_query_arg($query_args, $url),
        ];

        return $request;

    }
}