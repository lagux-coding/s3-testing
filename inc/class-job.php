<?php

class S3Testing_Job
{
    public $job = [];
    public $start_time = 0;
    public $temp = [];
    public $backup_folder = '';
    public $backup_file_name = '';

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
}