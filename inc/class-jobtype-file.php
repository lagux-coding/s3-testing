<?php

class S3Testing_JobType_File extends S3Testing_JobTypes
{
    public function __construct()
    {
        $this->info['ID'] = 'FILE';
        $this->info['name'] = 'Files';
        $this->info['description'] = 'File backup';
        $this->info['URI'] = '';
        $this->info['author'] = 'WP Media';
        $this->info['authorURI'] = '';
        $this->info['version'] = S3Testing::get_plugin_data('Version');
    }

    public function creates_file()
    {
        return true;
    }

    public function option_defaults()
    {

        return [
            'backupexcludethumbs' => false,
            'backupspecialfiles' => true,
            'backuproot' => true,
            'backupcontent' => true,
            'backupplugins' => true,
            'backupthemes' => true,
            'backupuploads' => true,
            'backuprootexcludedirs' => [],
            'backupcontentexcludedirs' => [],
            'backuppluginsexcludedirs' => [],
            'backupthemesexcludedirs' => [],
            'backupuploadsexcludedirs' => [],
            'fileexclude' => '',
            'dirinclude' => '',
            'backupabsfolderup' => false,
        ];
    }

    public function edit_tab($main)
    {
        $abs_path = realpath(S3Testing_Path_Fixer::fix_path(ABSPATH));
        $abs_path = dirname($abs_path); ?>
        <h3 class="title"><?php esc_html_e('Folders to backup', 's3testing'); ?></h3>
        <p></p>
        <table class="form-table">
            <tr>
                <th scope="row"><label
                            for="idbackuproot"><?php esc_html_e('Backup Wordpress install folder'); ?></label></th>
                <td>
                    <?php
                    $this->show_folder('root', $main, ABSPATH);
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="idbackupcontent"><?php esc_html_e('Backup content folder'); ?></label></th>
                <td>
                    <?php
                    $this->show_folder('content', $main, WP_CONTENT_DIR);
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function edit_form_post_save($id)
    {
        $boolean_fields_def = [
            'backuproot' => FILTER_VALIDATE_BOOLEAN,
            'backupcontent' => FILTER_VALIDATE_BOOLEAN,
        ];

        $boolean_data = filter_input_array(INPUT_POST, $boolean_fields_def);
        $boolean_data or $boolean_data = [];

        foreach ($boolean_fields_def as $key => $value) {
            S3Testing_Option::update($id, $key, !empty($boolean_data[$key]));
        }

        unset($boolean_fields_def, $boolean_data);
    }

    private function show_folder($id, $jobid, $path)
    {
        $folder = realpath(S3Testing_Path_Fixer::fix_path($path));

        if ($folder) {
            $folder = untrailingslashit(str_replace('\\', '/', $folder));
        }
        ?>
        <input class="checkbox"
               type="checkbox"<?php checked(S3Testing_Option::get($jobid, 'backup' . $id)); ?>
               name="backup<?php echo esc_attr($id); ?>"
               id="idbackup<?php echo esc_attr($id); ?>"
               value="1"/>
        <code title="<?php echo esc_attr(sprintf(__('Path as set by user (symlink?): %s'), $path)); ?>"><?php echo esc_attr($folder); ?></code>
        <?php
    }

    public function job_run(S3Testing_Job $job_object)
    {
        $abs_path = realpath(S3Testing_Path_Fixer::fix_path(ABSPATH));
        $abs_path = trailingslashit(str_replace('\\', '/', $abs_path));

        $job_object->temp['folders_to_backup'] = [];
        $folders_already_in = $job_object->get_folders_to_backup();

        //backup root
        if($abs_path && !empty($job_object->job['backuproot'])) {
            $abs_path = trailingslashit(str_replace('\\', '/', $abs_path));
            $excludes = $this->get_exclude_dirs($abs_path, $folders_already_in);

            $this->get_folder_list($job_object, $abs_path, $excludes);
        }
        return true;
    }

    private function get_exclude_dirs($folder, $excludedir = [])
    {
        $folder = trailingslashit(str_replace('\\', '/', realpath(S3Testing_Path_Fixer::fix_path($folder))));

        if (false !== strpos(trailingslashit(str_replace('\\', '/', realpath(WP_CONTENT_DIR))), $folder) && trailingslashit(str_replace('\\', '/', realpath(WP_CONTENT_DIR))) != $folder) {
            $excludedir[] = trailingslashit(str_replace('\\', '/', realpath(WP_CONTENT_DIR)));
        }
        if (false !== strpos(trailingslashit(str_replace('\\', '/', realpath(WP_PLUGIN_DIR))), $folder) && trailingslashit(str_replace('\\', '/', realpath(WP_PLUGIN_DIR))) != $folder) {
            $excludedir[] = trailingslashit(str_replace('\\', '/', realpath(WP_PLUGIN_DIR)));
        }
        if (false !== strpos(trailingslashit(str_replace('\\', '/', realpath(get_theme_root()))), $folder) && trailingslashit(str_replace('\\', '/', realpath(get_theme_root()))) != $folder) {
            $excludedir[] = trailingslashit(str_replace('\\', '/', realpath(get_theme_root())));
        }
        if (false !== strpos(trailingslashit(str_replace('\\', '/', realpath(S3Testing_File::get_upload_dir()))), $folder) && trailingslashit(str_replace('\\', '/', realpath(S3Testing_File::get_upload_dir()))) != $folder) {
            $excludedir[] = trailingslashit(str_replace('\\', '/', realpath(S3Testing_File::get_upload_dir())));
        }

        return array_unique($excludedir);

    }

    private function get_folder_list(&$job_object, $folder, $excludedirs = [])
    {
        try {
            $dir = new S3Testing_Directory($folder);

            //add folder to folder list
            $job_object->add_folders_to_backup($folder);

            foreach ($dir as $file) {
                if ($file->isDot()) {
                    continue;
                }
                $path = str_replace('\\', '/', realpath($file->getPathname()));

                if ($file->isDir()) {
                    if (in_array(trailingslashit($path), $excludedirs, true)) {
                        continue;
                    }
                    if (file_exists(trailingslashit($file->getPathname()) . '.donotbackup')) {
                        continue;
                    }

                    $this->get_folder_list($job_object, trailingslashit($path), $excludedirs);

                }
            }
        } catch (UnexpectedValueException $e) {
            //do nothing
        }

        return true;
    }
}