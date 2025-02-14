<?php

class S3Testing_Job
{
    public $job = [];
    public $start_time = 0;
    public $temp = [];
    public $backup_folder = '';
    public $backup_file = '';

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

        foreach($file_data as $folder) {
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

        if(!in_array($starttype, [
            'runnowlink',
        ],
        true)) {
            return wp_remote_post($request['url']);
        }

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

    public function run()
    {
        $job_types = S3Testing::get_job_types();
        $job_types['FILE']->job_run($this);

        $this->create_archive();

//        S3Testing::get_destination('S3')->job_run_archive($this);
    }

    private function create_archive()
    {
        $folders_to_backup = $this->get_folders_to_backup();

        if(is_file($this->backup_folder . $this->backup_file)) {
            unlink($this->backup_folder . $this->backup_file);
        }

        try {
            $backup_archive = new S3Testing_Create_Archive($this->backup_folder . $this->backup_file);

            while ($folder = array_shift($folders_to_backup)) {
                if(in_array($folder, $folders_to_backup, true)) {
                    continue;
                }

                $files_in_folder = $this->get_files_in_folder($folder);

                if (empty($files_in_folder)) {
                    $folder_name_in_archive = trim(ltrim($this->get_destination_path_replacement($folder), '/'));

                    if (!empty($folder_name_in_archive)) {
                        $backup_archive->add_empty_folder($folder, $folder_name_in_archive);
                    }

                    continue;
                }

                while ($file = array_shift($files_in_folder)) {
                    //generate filename in archive
                    $in_archive_filename = ltrim($this->get_destination_path_replacement($file), '/');

                    //add file to archive
                    if ($backup_archive->add_file($file, $in_archive_filename)) {

                    }else {
                        $backup_archive->close();
                        unset($backup_archive);

                        return false;
                    }
                }
            }
            $backup_archive->close();
            unset($backup_archive);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function get_destination_path_replacement($path)
    {
        $abs_path = realpath(S3Testing_Path_Fixer::fix_path(ABSPATH));

        $abs_path = trailingslashit(str_replace('\\', '/', $abs_path));

        $path = str_replace(['\\', $abs_path], '/', (string) $path);

        //replace the colon from windows drive letters with so they will not be problems with them in archives or on copying to directory
        if (0 === stripos(PHP_OS, 'WIN') && 1 === strpos($path, ':/')) {
            $path = '/' . substr_replace($path, '', 1, 1);
        }

        return $path;
    }

    public function get_files_in_folder($folder)
    {
        $files = [];
        $folder = trailingslashit($folder);

        if (!is_dir($folder)) {

            return $files;
        }

        if (!is_readable($folder)) {

            return $files;
        }

        try {
            $dir = new S3Testing_Directory($folder);

            foreach ($dir as $file) {
                if ($file->isDot() || $file->isDir()) {
                    continue;
                }

                $path = S3Testing_Path_Fixer::slashify($file->getPathname());

                $files[] = S3Testing_Path_Fixer::slashify(realpath($path));

            }
        } catch (Exception $e) {
        }
        return $files;
    }

    public function generate_filename($name, $suffix = '', $delete_temp_file = true)
    {
        if ($suffix) {
            $suffix = '.' . trim($suffix, '. ');
        }
        $name .= $suffix;

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