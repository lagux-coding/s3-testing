<?php

class S3Testing_Job
{
    public $job = [];
    public $start_time = 0;
    public $temp = [];
    public $backup_folder = '';
    public $backup_filesize = 0;
    public $backup_file = '';
    public $steps_todo = ['CREATE'];
    public $steps_done = [];
    public $steps_data = [];
    public $step_working = 'CREATE';
    public $substeps_todo = 0;
    public $substeps_done = 0;
    public $step_percent = 1;
    public $substep_percent = 1;
    public $count_folder = 0;
    public $count_files = 0;
    public $count_files_size = 0;
    private $timestamp_script_start = 0;
    public $additional_files_to_backup = [];

    public $timestamp_last_update = 0;

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

        $file_data = file_get_contents(S3Testing::get_plugin_data('running_file'), false, null, 8);
        if (empty($file_data)) {
            return false;
        }

        if ($job_object = unserialize($file_data)) {
            if ($job_object instanceof S3Testing_Job) {
                return $job_object;
            }
        }

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
        $this->count_folder = count($folders);

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

        $this->start_time = current_time('timestamp');
        //write settings to job
        S3Testing_Option::update( $this->job['jobid'], 'lastrun', $this->start_time );

        $this->timestamp_last_update = microtime( true );

        //setup job steps
        $this->steps_data['CREATE']['CALLBACK'] = '';
        $this->steps_data['CREATE']['NAME'] = __('Job Start');
        $this->steps_data['CREATE']['STEP_TRY'] = 0;

        $job_need_dest = false;
        if ($job_types = S3Testing::get_job_types()) {
            foreach ($job_types as $id => $job_type_class) {
                if (in_array($id, $this->job['type'], true) && $job_type_class->creates_file()) {
                    $this->steps_todo[] = 'JOB_' . $id;
                    $this->steps_data['JOB_' . $id]['NAME'] =$job_type_class->info['description'];
                    $this->steps_data['JOB_' . $id]['STEP_TRY'] = 0;
                    $this->steps_data['JOB_' . $id]['SAVE_STEP_TRY'] = 0;
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
                $this->steps_todo[] = 'CREATE_ARCHIVE';
                $this->steps_data['CREATE_ARCHIVE']['NAME'] = __('Create Archive');
                $this->steps_data['CREATE_ARCHIVE']['STEP_TRY'] = 0;
                $this->steps_data['CREATE_ARCHIVE']['SAVE_STEP_TRY'] = 0;
                $this->backup_file = $this->generate_filename($this->job['archivename'], $this->job['archiveformat']);
            }

            foreach(S3Testing::get_registered_destinations() as $id => $dest) {
                if (!in_array($id, $this->job['destinations'], true) || empty($dest['class'])) {
                    continue;
                }
                $dest_class = S3Testing::get_destination($id);
                if ($dest_class->can_run($this->job)) {
                    $this->steps_todo[] = 'DEST_' . $id;
                    $this->steps_data['DEST_' . $id]['NAME'] = $dest['info']['description'];
                    $this->steps_data['DEST_' . $id]['STEP_TRY'] = 0;
                    $this->steps_data['DEST_' . $id]['SAVE_STEP_TRY'] = 0;
                }
            }
        }
        $this->steps_todo[] = 'END';
        $this->steps_data['END']['NAME'] = __('End of Job');
        $this->steps_data['END']['STEP_TRY'] = 1;
        $this->write_running_file();

        if(!empty($this->backup_folder)) {
            $folder_message = S3Testing_File::check_folder($this->backup_folder, true);
            if (!empty($folder_message)) {
                $this->steps_todo = ['END'];
            }
        }

        $this->steps_done[] = 'CREATE';
    }

    public function run()
    {
        // Job can't run it is not created
        if (empty($this->steps_todo)) {
            $running_file = S3Testing::get_plugin_data('running_file');
            if (file_exists($running_file)) {
                unlink($running_file);
            }

            return;
        }

        $this->timestamp_script_start = microtime(true);

        $this->write_running_file();

        $job_types = S3Testing::get_job_types();

        //go step by step
        foreach($this->steps_todo as $this->step_working) {
            //check if step already done
            if(in_array($this->step_working, $this->steps_done, true)) {
                continue;
            }

            //calc step percent
            if(count($this->steps_done) > 0) {
                $this->step_percent = min(
                    round(count($this->steps_done) / count($this->steps_todo) * 100),
                    100
                );
            } else {
                $this->step_percent = 1;
            }
            //do step tries
            while(true) {
                if($this->steps_data[$this->step_working]['STEP_TRY'] >= get_site_option('s3testing_cfg_jobstepretry')) {
                    $this->temp = [];
                    $this->steps_done[] = $this->step_working;
                    $this->substeps_done = 0;
                    $this->substeps_todo = 0;
                    $this->do_restart();
                    break;
                }

                ++$this->steps_data[$this->step_working]['STEP_TRY'];
                $done = false;

                if($this->step_working == 'CREATE_ARCHIVE') {
                    $done = $this->create_archive();
                } elseif ($this->step_working == 'END') {
                    $this->end();
                    break 2;
                } elseif (strstr((string) $this->step_working, 'JOB_')) {
                    $done = $job_types[str_replace('JOB_', '', (string) $this->step_working)]->job_run($this);
                } elseif (strstr((string) $this->step_working, 'DEST_')) {
                    $done = S3Testing::get_destination(str_replace('DEST_', '', (string) $this->step_working))
                        ->job_run_archive($this);
                } elseif (!empty($this->steps_data[$this->step_working]['CALLBACK'])) {
                    $done = $this->steps_data[$this->step_working]['CALLBACK']($this);
                }

                if ($done === true) {
                    $this->temp = [];
                    $this->steps_done[] = $this->step_working;
                    $this->substeps_done = 0;
                    $this->substeps_todo = 0;
                    $this->update_working_data(true);
                }

                if (count($this->steps_done) < count($this->steps_todo) - 1) {
                    $this->do_restart();
                }

                if ($done === true) {
                    break;
                }
            }
        }
    }

    public function do_restart($must = false)
    {
        if ($this->step_working === 'END' || (count($this->steps_done) + 1) >= count($this->steps_todo)) {
            return;
        }

        return;
    }

    private function end()
    {
        $this->step_working = 'END';
        $this->substeps_todo = 1;

        //set done
        $this->substeps_done = 1;
        $this->steps_done[] = 'END';

        self::clean_temp_folder();
        exit();
    }

    public static function clean_temp_folder()
    {
        $instance = new self();
        $temp_dir = S3Testing::get_plugin_data('TEMP');
        $do_not_delete_files = ['.htaccess', 'nginx.conf', 'index.php', '.', '..', '.donotbackup'];
        if (is_writable($temp_dir)) {
            try {
                $dir = new S3Testing_Directory($temp_dir);

                foreach ($dir as $file) {
                    if (in_array(
                            $file->getFilename(),
                            $do_not_delete_files,
                            true
                        ) || $file->isDir() || $file->isLink()) {
                        continue;
                    }
                    if ($file->isWritable()) {
                        unlink($file->getPathname());
                    }
                }
            } catch (UnexpectedValueException $e) {

            }
        }
    }

    private function create_archive()
    {
        //load folders to backup
        $folders_to_backup = $this->get_folders_to_backup();

        $this->substeps_todo = $this->count_folder + 1;

        //initial settings for restarts in archiving
        if (!isset($this->steps_data[$this->step_working]['on_file'])) {
            $this->steps_data[$this->step_working]['on_file'] = '';
        }
        if (!isset($this->steps_data[$this->step_working]['on_folder'])) {
            $this->steps_data[$this->step_working]['on_folder'] = '';
        }

        if ($this->steps_data[$this->step_working]['on_folder'] == '' && $this->steps_data[$this->step_working]['on_file'] == '' && is_file($this->backup_folder . $this->backup_file)) {
            unlink($this->backup_folder . $this->backup_file);
        }

        try {
            $backup_archive = new S3Testing_Create_Archive($this->backup_folder . $this->backup_file);

            //add extra file
            if ($this->substeps_done == 0) {
                if (!empty($this->additional_files_to_backup) && $this->substeps_done == 0) {
                    foreach ($this->additional_files_to_backup as $file) {
                        $archiveFilename = ltrim($this->get_destination_path_replacement(ABSPATH . basename($file)), '/');
                        if ($backup_archive->add_file($file, $archiveFilename)) {
                            ++$this->count_files;
                            $this->count_files_size = $this->count_files_size + filesize($file);
                            $this->update_working_data();
                        } else {
                            $backup_archive->close();
                            $this->steps_data[$this->step_working]['on_file'] = '';
                            $this->steps_data[$this->step_working]['on_folder'] = '';

                            return false;
                        }
                    }
                }
                ++$this->substeps_done;
            }

            //add normal files
            while ($folder = array_shift($folders_to_backup)) {
                if(in_array($this->steps_data[$this->step_working]['on_folder'], $folders_to_backup, true)) {
                    continue;
                }

                $this->steps_data[$this->step_working]['on_folder'] = $folder;
                $files_in_folder = $this->get_files_in_folder($folder);

                if (empty($files_in_folder)) {
                    $folder_name_in_archive = trim(ltrim($this->get_destination_path_replacement($folder), '/'));

                    if (!empty($folder_name_in_archive)) {
                        $backup_archive->add_empty_folder($folder, $folder_name_in_archive);
                    }

                    continue;
                }

                //add files
                while ($file = array_shift($files_in_folder)) {
                    if (in_array($this->steps_data[$this->step_working]['on_file'], $files_in_folder, true)) {
                        continue;
                    }

                    $this->steps_data[$this->step_working]['on_file'] = $file;
                    //generate filename in archive
                    $in_archive_filename = ltrim($this->get_destination_path_replacement($file), '/');

                    //add file to archive
                    if ($backup_archive->add_file($file, $in_archive_filename)) {
                        ++$this->count_files;
                        $this->count_files_size = $this->count_files_size + filesize($file);
                        $this->update_working_data();
                    }else {
                        $backup_archive->close();
                        unset($backup_archive);
                        $this->steps_data[$this->step_working]['on_file'] = '';
                        $this->steps_data[$this->step_working]['on_folder'] = '';
                        $this->substeps_done = 0;
                        $this->backup_filesize = filesize($this->backup_folder . $this->backup_file);
                        if ($this->backup_filesize === false) {
                            $this->backup_filesize = PHP_INT_MAX;
                        }

                        return false;
                    }
                }
                $this->steps_data[$this->step_working]['on_file'] = '';
                ++$this->substeps_done;
            }
            $backup_archive->close();
            unset($backup_archive);

        } catch (Exception $e) {
            return false;
        }
        $this->backup_filesize = filesize($this->backup_folder . $this->backup_file);
        if ($this->backup_filesize === false) {
            $this->backup_filesize = PHP_INT_MAX;
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
        }
    }

    public function update_working_data($must = false)
    {
        global $wpdb;

        $time_to_update = microtime(true) - $this->timestamp_last_update;
        if ($time_to_update < 1 && !$must) {
            return;
        }

        @set_time_limit(300);

        if (!file_exists(S3Testing::get_plugin_data('running_file'))) {
            if ($this->step_working !== 'END') {
                $this->end();
            }
        } else {
            $this->timestamp_last_update = microtime(true); //last update of working file
            $this->write_running_file();
        }
    }

    public function generate_db_dump_filename($name, $suffix = '')
    {
        $name = (string) apply_filters( 's3testing_generate_dump_filename', $name );

        return $this->generate_filename( $name, $suffix );

    }

    public static function sanitize_file_name($filename)
    {
        $filename = trim((string) $filename);

        $special_chars = [
            '?',
            '[',
            ']',
            '/',
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
        ];

        $filename = str_replace($special_chars, '', $filename);

        $filename = str_replace([' ', '%20', '+'], '_', $filename);
        $filename = str_replace(["\n", "\t", "\r"], '-', $filename);

        return trim($filename, '.-_');
    }

    public function get_restart_time()
    {

        $job_max_execution_time = get_site_option('s3testing_cfg_jobmaxexecutiontime');

        if (empty($job_max_execution_time)) {
            return 300;
        }

        $execution_time = microtime(true) - $this->timestamp_script_start;

        return $job_max_execution_time - $execution_time - 3;
    }
}