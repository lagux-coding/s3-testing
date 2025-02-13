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
        //check folder
        $folder_msg_temp = S3Testing_File::check_folder(S3Testing::get_plugin_data('temp'), true);

        if(!empty($folder_msg_temp)) {
            S3Testing_Admin::message($folder_msg_temp, true);

            return false;
        }

        $random = random_int(10, 90) * 10000;
        usleep($random);

        $s3testing_job_object = self::get_working_data();
        $starttype_exists = in_array(
            $starttype,
            [
                'runnow',
            ],
            true
        );

        if(!$s3testing_job_object && $starttype_exists && $jobid) {
            $s3testing_job_object = new self();
            $s3testing_job_object->create($starttype, $jobid);
        }

        if($s3testing_job_object) {
            $s3testing_job_object->run();
        }
    }

    public static function get_working_data()
    {
        clearstatcache(true, S3Testing::get_plugin_data('running_file'));

        if (!file_exists(S3Testing::get_plugin_data('running_file'))) {
            return false;
        }

//        $file_data = file_get_contents(S3Testing::get_plugin_data('running_file'), false, null, 8);
//
//        if (empty($file_data)) {
//            return false;
//        }

        return false;
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

    private function create($start_type, $job_id = 0)
    {
        if (!in_array(
            $start_type,
            ['runnow'],
            true)) {
            return;
        }

        if ($job_id) {
            $this->job = S3Testing_Option::get_job($job_id);
        } else {
            return;
        }

        $job_need_dest = false;
        if ($job_types = S3Testing::get_job_types()) {
            foreach ($job_types as $id => $job_type_class) {
                if (in_array($id, $this->job['type'], true) && $job_type_class->creates_file()) {
                    $job_need_dest = true;
                }
            }
        }
        if ($job_need_dest) {
            if ($this->job['backuptype'] == 'archive') {
                if (!$this->backup_folder || $this->backup_folder == '/') {
                    $this->backup_folder = S3Testing::get_plugin_data('TEMP');
                }

                //create backup filename
                $this->backup_file = $this->generate_filename($this->job['archivename'], $this->job['archiveformat']);
            }
        }

        $this->write_running_file();

    }

    public function generate_filename($name, $suffix = '', $delete_temp_file = true)
    {
        if ($suffix) {
            $suffix = '.' . trim($suffix, '. ');
        }

        if ($delete_temp_file && is_writeable(S3Testing::get_plugin_data('TEMP') . $name) && !is_dir(S3Testing::get_plugin_data('TEMP') . $name) && !is_link(S3Testing::get_plugin_data('TEMP') . $name)) {
            unlink(S3Testing::get_plugin_data('TEMP') . $name);
        }

        return $name;
    }

    private function write_running_file()
    {
        $clone = clone $this;
        $data = '<?php //' . serialize($clone);

        $write = file_put_contents(S3Testing::get_plugin_data('running_file'), $data);
        if (!$write || $write < strlen($data)) {
            unlink(S3Testing::get_plugin_data('running_file'));
            $this->log(__('Cannot write progress to working file. Job will be aborted.'), E_USER_ERROR);
        }
    }
}