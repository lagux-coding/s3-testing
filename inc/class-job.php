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
    public $user_abort = false;
    public $logfile = '';
    public $lastmsg = '';
    public $lasterrormsg = '';
    public $warnings = 0;
    public $errors = 0;

    public static function start_http($starttype, $jobid = 0)
    {
        //check folder
        $folder_msg_temp = S3Testing_File::check_folder(S3Testing::get_plugin_data('temp'), true);

        if(!empty($folder_msg_temp)) {
            S3Testing_Admin::message($folder_msg_temp, true);

            return false;
        }

        if ($starttype !== 'restart') {
            //check job id exists
            if ((int) $jobid !== (int) S3Testing_Option::get($jobid, 'jobid')) {
                return false;
            }

            //check folders
            $log_folder = get_site_option('s3testing_cfg_logfolder');
            $folder_message_log = S3Testing_File::check_folder(S3Testing_File::get_absolute_path($log_folder));
            $folder_message_temp = S3Testing_File::check_folder(S3Testing::get_plugin_data('TEMP'), true);
            if (!empty($folder_message_log) || !empty($folder_message_temp)) {
                S3Testing_Admin::message($folder_message_log, true);
                S3Testing_Admin::message($folder_message_temp, true);

                return false;
            }
        }

        $random = random_int(10, 90) * 10000;
        usleep($random);

        $s3testing_job_object = self::get_working_data();
        $starttype_exists = in_array(
            $starttype,
            [
                'runnow',
                'cronrun',
            ],
            true
        );

        if(!$s3testing_job_object && $starttype_exists && $jobid) {
            // Schedule restart event.
            wp_schedule_single_event(time() + 60, 's3testing_cron', ['arg' => 'restart']);

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

        sleep(4);

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

        if (in_array($starttype, ['runnow', 'cronrun'], true)) {
            $query_args['s3testing_run'] = $starttype;
        }

        if (in_array($starttype, ['runnowlink', 'runnow', 'cronrun'], true) && !empty($jobid)) {
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
            ['runnow', 'cronrun'],
            true)) {
            return;
        }

        if ($job_id) {
            $this->job = S3Testing_Option::get_job($job_id);
        } else {
            return;
        }

        $this->start_time = current_time('timestamp');

        //set log file
        $log_folder = get_site_option('s3testing_cfg_logfolder');
        $log_folder = S3Testing_File::get_absolute_path($log_folder);
        $this->logfile = $log_folder . 's3testing_log_' . S3Testing::get_plugin_data('hash') . date(
                'Y-m-d_H-i-s',
                current_time('timestamp')
            ) . '.html';

        //write settings
        S3Testing_Option::update( $this->job['jobid'], 'lastrun', $this->start_time );
        S3Testing_Option::update($this->job['jobid'], 'logfile', $this->logfile);

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
                    $this->steps_data['JOB_' . $id]['NAME'] = $job_type_class->info['description'];
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
                $this->steps_data['CREATE_ARCHIVE']['NAME'] = __('Creates Archive');
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

        //create log file
        $head = '';
        $info = '';
        $head .= '<!DOCTYPE html>' . PHP_EOL;
        $head .= '<html lang="' . str_replace('_', '-', get_locale()) . '">' . PHP_EOL;
        $head .= '<head>' . PHP_EOL;
        $head .= '<meta charset="' . get_bloginfo('charset') . '" />' . PHP_EOL;
        $head .= '<title>' . sprintf(
                __('S3Testing log for %1$s from %2$s at %3$s'),
                esc_attr($this->job['name']),
                date_i18n(get_option('date_format')),
                date_i18n(get_option('time_format'))
            ) . '</title>' . PHP_EOL;
        $head .= '<meta name="robots" content="noindex, nofollow" />' . PHP_EOL;
        $head .= '<meta name="copyright" content="Copyright &copy; 2012 - ' . date('Y') . ' WP Media, Inc." />' . PHP_EOL;
        $head .= '<meta name="author" content="WP Media" />' . PHP_EOL;
        $head .= '<meta name="generator" content="S3Testing ' . S3Testing::get_plugin_data('Version') . '" />' . PHP_EOL;
        $head .= '<meta http-equiv="cache-control" content="no-cache" />' . PHP_EOL;
        $head .= '<meta http-equiv="pragma" content="no-cache" />' . PHP_EOL;
        $head .= '<meta name="date" content="' . date('c') . '" />' . PHP_EOL;
        $head .= str_pad('<meta name="s3testing_errors" content="0" />', 100) . PHP_EOL;
        $head .= str_pad('<meta name="s3testing_warnings" content="0" />', 100) . PHP_EOL;
        $head .= '<meta name="s3testing_jobid" content="' . $this->job['jobid'] . '" />' . PHP_EOL;
        $head .= '<meta name="s3testing_jobname" content="' . esc_attr($this->job['name']) . '" />' . PHP_EOL;
        $head .= '<meta name="s3testing_jobtype" content="' . esc_attr(implode('+', $this->job['type'])) . '" />' . PHP_EOL;
        $head .= str_pad('<meta name="s3testing_backupfilesize" content="0" />', 100) . PHP_EOL;
        $head .= str_pad('<meta name="s3testing_jobruntime" content="0" />', 100) . PHP_EOL;
        $head .= '</head>' . PHP_EOL;
        $head .= '<body style="margin:0;padding:3px;font-family:monospace;font-size:12px;line-height:15px;background-color:black;color:#c0c0c0;white-space:nowrap;">' . PHP_EOL;
        $info .= sprintf(
                _x(
                    '[INFO] %1$s %2$s; A project of Me',
                    'Plugin name; Plugin Version; plugin url',
                ),
                S3Testing::get_plugin_data('name'),
                S3Testing::get_plugin_data('Version'),
            ) . '<br />' . PHP_EOL;
        $info .= sprintf(
                _x('[INFO] WordPress %1$s on %2$s', 'WordPress Version; Blog url'),
                S3Testing::get_plugin_data('wp_version'),
                esc_attr(site_url('/'))
            ) . '<br />' . PHP_EOL;
        $job_name = esc_attr($this->job['name']);
        $info .= sprintf(__('[INFO] S3Testing job: %1$s'), $job_name) . '<br />' . PHP_EOL;
        $logfile = basename($this->logfile);
        $info .= sprintf(__('[INFO] Logfile is: %s'), $logfile) . '<br />' . PHP_EOL;
        $backupfile = $this->backup_file;
        $info .= sprintf(__('[INFO] Backup file is: %s'), $backupfile) . '<br />' . PHP_EOL;

        if (!file_put_contents($this->logfile, $head . $info, FILE_APPEND)) {
            $this->logfile = '';
        }

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
        $this->log('This is a test');

        //disable output buffering
        if ($level = ob_get_level()) {
            for ($i = 0; $i < $level; ++$i) {
                ob_end_clean();
            }
        }

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
                    $this->log(__('Step aborted: too many attempts!'), E_USER_ERROR);
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

        if (!file_exists(S3Testing::get_plugin_data('running_file'))) {
            return;
        }

        return;
    }

    private function end()
    {


        $this->step_working = 'END';
        $this->substeps_todo = 1;

        if (!file_exists(S3Testing::get_plugin_data('running_file'))) {
            $this->log(__('Aborted by user!'), E_USER_ERROR);
        }

        //delete old logs
        if (get_site_option('s3testing_cfg_maxlogs')) {
            $log_file_list = [];
            $log_folder = trailingslashit(dirname($this->logfile));
            if (is_readable($log_folder)) { //make file list
                try {
                    $dir = new S3Testing_Directory($log_folder);

                    foreach ($dir as $file) {
                        if (!$file->isDot() && strpos($file->getFilename(), 's3testing_log_') === 0 && strpos($file->getFilename(), '.html') !== false) {
                            $log_file_list[$file->getMTime()] = clone $file;
                        }
                    }
                } catch (UnexpectedValueException $e) {
                    $this->log(sprintf(__('Could not open path: %s'), $e->getMessage()), E_USER_WARNING);
                }
            }
            if (count($log_file_list) > 0) {
                krsort($log_file_list, SORT_NUMERIC);
                $num_delete_files = 0;
                $i = -1;

                foreach ($log_file_list as $log_file) {
                    ++$i;
                    if ($i < get_site_option('s3testing_cfg_maxlogs')) {
                        continue;
                    }
                    unlink($log_file->getPathname());
                    ++$num_delete_files;
                }
                if ($num_delete_files > 0) {
                    $this->log(sprintf(
                        _n(
                            'One old log deleted',
                            '%d old logs deleted',
                            $num_delete_files,
                        ),
                        $num_delete_files
                    ));
                }
            }
        }

        //update job options
        $this->job['lastruntime'] = current_time('timestamp') - $this->start_time;
        S3Testing_Option::update($this->job['jobid'], 'lastruntime', $this->job['lastruntime']);

        //set done
        $this->substeps_done = 1;
        $this->steps_done[] = 'END';

        self::clean_temp_folder();

        file_put_contents($this->logfile, '</body>' . PHP_EOL . '</html>', FILE_APPEND);

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

        if ($this->steps_data[$this->step_working]['SAVE_STEP_TRY'] != $this->steps_data[$this->step_working]['STEP_TRY']) {
            $this->log(
                sprintf(
                    __('%d. Trying to create backup archive &hellip;'),
                    $this->steps_data[$this->step_working]['STEP_TRY']
                ),
                E_USER_NOTICE
            );
        }

        try {
            $backup_archive = new S3Testing_Create_Archive($this->backup_folder . $this->backup_file);

            if ($this->substeps_done == 0) {
                $this->log(sprintf(
                    _x(
                        'Compressing files as %s. Please be patient, this may take a moment.',
                        'Archive compression method',
                    ),
                    $backup_archive->get_method()
                ));
            }

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
            $this->log(__('Backup archive created.'), E_USER_NOTICE);
        } catch (Exception $e) {
            return false;
        }
        $this->backup_filesize = filesize($this->backup_folder . $this->backup_file);
        if ($this->backup_filesize === false) {
            $this->backup_filesize = PHP_INT_MAX;
        }

        if ($this->backup_filesize >= PHP_INT_MAX) {
            $this->log(
                __(
                    'The Backup archive will be too large for file operations with this PHP Version.'
                ),
                E_USER_ERROR
            );
            $this->end();
        } else {
            $this->log(
                sprintf(__('Archive size is %s.'), size_format($this->backup_filesize, 2)),
                E_USER_NOTICE
            );
        }

        $this->log(
            sprintf(
                __('%1$d Files with %2$s in Archive.'),
                $this->count_files,
                size_format($this->count_files_size, 2)
            ),
            E_USER_NOTICE
        );

        $this->log(
            sprintf(
                __('Create backup archive done!')
            ),
            E_USER_NOTICE
        );

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

        $wpdb->check_connection(false);

        if (!file_exists(S3Testing::get_plugin_data('running_file'))) {
            if ($this->step_working !== 'END') {
                $this->end();
            }
        } else {
            $this->timestamp_last_update = microtime(true); //last update of working file
            $this->write_running_file();
        }
    }

    public function log($message, $type = E_USER_NOTICE)
    {
        if (error_reporting() == 0) {
            return true;
        }

        $error = false;
        $warning = false;

        switch ($type) {
            case E_NOTICE:
            case E_USER_NOTICE:
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $this->warnings++;
                $warning = true;
                $message = __('WARNING:') . ' ' . $message;
                break;
            case E_ERROR:
            case E_USER_ERROR:
                $this->errors++;
                $error = true;
                $message = __('ERROR:') . ' ' . $message;
                break;
            default:
                $message = $type . ': ' . $message;
                break;
        }

        //timestamp for log file
        $debug_info = '';
        $timestamp = '<span datetime="' . date('c') . '" ' . $debug_info . '>[' . date(
                'd-M-Y H:i:s',
                current_time('timestamp')
            ) . ']</span> ';

        //set last Message
        if ($error) {
            $output_message = '<span style="background-color:#ff6766;color:black;padding:0 2px;">' . esc_html($message) . '</span>';
            $this->lasterrormsg = $output_message;
        } elseif ($warning) {
            $output_message = '<span style="background-color:#ffc766;color:black;padding:0 2px;">' . esc_html($message) . '</span>';
            $this->lasterrormsg = $output_message;
        } else {
            $output_message = esc_html($message);
            $this->lastmsg = $output_message;
        }

        if ($this->logfile) {
            if (!file_put_contents(
                $this->logfile,
                $timestamp . $output_message . '<br />' . PHP_EOL,
                FILE_APPEND
            )) {
                $this->logfile = '';
                restore_error_handler();
                trigger_error(esc_html($message), $type);
            }
        }
        $this->update_working_data($error || $warning);
        return true;
    }

    public static function user_abort() {
        $job_object = S3Testing_Job::get_working_data();

        unlink(S3Testing::get_plugin_data('running_file'));

        //if job not working currently abort it this way for message
        $not_worked_time = microtime(true) - $job_object->timestamp_last_update;
        $restart_time = get_site_option('s3testing_cfg_jobmaxexecutiontime');
        if (empty($restart_time)) {
            $restart_time = 60;
        }

        try {
            if ($not_worked_time > $restart_time) {
                $job_object->user_abort = true;
                $job_object->update_working_data();
            }
        } catch (Exception $e) {

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

    public static function disable_caches()
    {
        //Special settings
        @putenv('nokeepalive=1');
        @ini_set('zlib.output_compression', 'Off');

        // deactivate caches
        if (!defined('DONOTCACHEDB')) {
            define('DONOTCACHEDB', true);
        }
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
    }
}