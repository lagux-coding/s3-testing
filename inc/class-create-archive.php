<?php

class S3Testing_Create_Archive
{
    private $file = '';
    private $method = '';
    private $ziparchive;
    private $pclzip;
    private $pclzip_file_list = [];
    private $filehandler;

    public function __construct($file)
    {
        if (!is_string($file) || empty($file)) {
            throw new S3Testing_Create_Archive_Exception(
                __('The file name of an archive cannot be empty.')
            );
        }

        // Check folder can used.
        if (!is_dir(dirname($file)) || !is_writable(dirname($file))) {
            throw new S3Testing_Create_Archive_Exception(
                sprintf(
                // translators: $1 is the file path
                    esc_html_x('Folder %s for archive not found', '%s = Folder name'),
                    dirname($file)
                )
            );
        }
        $this->file = trim($file);

        // TAR.GZ
        if (
            (!$this->filehandler && '.tar.gz' === strtolower(substr($this->file, -7)))
            || (!$this->filehandler && '.tar.bz2' === strtolower(substr($this->file, -8)))
        ) {
            if (!function_exists('gzencode')) {
                throw new S3Testing_Create_Archive_Exception(
                    __('Functions for gz compression not available')
                );
            }

            $this->method = 'TarGz';
            $this->handlertype = 'gz';
            $this->filehandler = $this->fopen($this->file, 'ab');
        }

        // .TAR
        if (!$this->filehandler && '.tar' === strtolower(substr($this->file, -4))) {
            $this->method = 'Tar';
            $this->filehandler = $this->fopen($this->file, 'ab');
        }

        // .ZIP
        if (!$this->filehandler && '.zip' === strtolower(substr($this->file, -4))) {
            $this->method = \ZipArchive::class;

            // Switch to PclZip if ZipArchive isn't supported.
            if (!class_exists(\ZipArchive::class)) {
                $this->method = \PclZip::class;
            }

            // GzEncode supported?
            if (\PclZip::class === $this->method && !function_exists('gzencode')) {
                throw new S3Testing_Create_Archive_Exception(
                    esc_html__('Functions for gz compression not available')
                );
            }

            if (\ZipArchive::class === $this->method) {
                $this->ziparchive = new ZipArchive();
                $ziparchive_open = $this->ziparchive->open($this->file, ZipArchive::CREATE);

                if ($ziparchive_open !== true) {
                    $this->ziparchive_status();

                    throw new S3Testing_Create_Archive_Exception(
                        sprintf(
                        // translators: $1 is a directory name
                            esc_html_x('Cannot create zip archive: %d', 'ZipArchive open() result'),
                            $ziparchive_open
                        )
                    );
                }
            }

            // Must be set to true to prevent issues. Monkey patch.
            $this->filehandler = true;
        }

        // .GZ
        if (
            (!$this->filehandler && '.gz' === strtolower(substr($this->file, -3)))
            || (!$this->filehandler && '.bz2' === strtolower(substr($this->file, -4)))
        ) {
            if (!function_exists('gzencode')) {
                throw new S3Testing_Create_Archive_Exception(
                    __('Functions for gz compression not available')
                );
            }

            $this->method = 'gz';
            $this->handlertype = 'gz';
            $this->filehandler = $this->fopen($this->file, 'w');
        }

//        if ('' === $this->method) {
//            throw new S3Testing_Create_Archive_Exception(
////                sprintf(
////                    esc_html_x('Method to archive file %s not detected', '%s = file name'),
////                    basename($this->file)
////                )
//            );
//        }

//        if (null === $this->filehandler) {
//            throw new S3Testing_Create_Archive_Exception(__('Cannot open archive file'));
//        }
    }

    public function add_empty_folder($folder_name, $name_in_archive)
    {
        $folder_name = trim($folder_name);

        if (empty($folder_name)) {

            return false;
        }

        if (!is_dir($folder_name) || !is_readable($folder_name)) {

            return false;
        }

        if (empty($name_in_archive)) {
            return false;
        }

        $name_in_archive = remove_invalid_characters_from_directory_name($name_in_archive);

        switch ($this->method) {
            case 'gz':

                return false;
                break;

            case 'Tar':

            case \ZipArchive::class:
                if (!$this->ziparchive->addEmptyDir($name_in_archive)) {

                    return false;
                }
                break;
        }

        return true;

    }

    public function add_file($file_name, $name_in_archive = '')
    {
        $file_name = trim($file_name);
        if (!is_string($file_name) || empty($file_name)) {

            return false;
        }

        clearstatcache(true, $file_name);

        if (!is_readable($file_name)) {

            return true;
        }

        if (empty($name_in_archive)) {
            $name_in_archive = $file_name;
        }

        switch ($this->method) {

            case \ZipArchive::class:
                // Convert chars for archives file names.
                if (function_exists('iconv') && stripos(PHP_OS, 'win') === 0) {
                    $test = @iconv('UTF-8', 'CP437', $name_in_archive);
                    if ($test) {
                        $name_in_archive = $test;
                    }
                }

                $file_size = filesize($file_name);
                if (false === $file_size) {
                    return false;
                }

                $zip_file_stat = $this->ziparchive->statName($name_in_archive);
                // If the file is allready in the archive doing anything else.
                if (isset($zip_file_stat['size']) && $zip_file_stat['size'] === $file_size) {
                    return true;
                }

                // The file is in the archive but the size is different than the one we
                // want to store. So delete the old and store the new one.
                if ($zip_file_stat) {
                    $this->ziparchive->deleteName($name_in_archive);
                    // Reopen on deletion.
                    $this->file_count = 21;
                }

                // Close and reopen, all added files are open on fs.
                // 35 works with PHP 5.2.4 on win.
                if ($this->file_count > 20) {
                    if (!$this->ziparchive->close()) {
                        sleep(1);
                    }

                    $this->ziparchive = null;

                    $this->ziparchive = new ZipArchive();
                    $ziparchive_open = $this->ziparchive->open($this->file, ZipArchive::CREATE);

                    if ($ziparchive_open !== true) {

                        return false;
                    }

                    $this->file_count = 0;
                }

                    if (!$this->ziparchive->addFromString($name_in_archive, file_get_contents($file_name))) {

                        return false;
                    } else {
                        if (!$this->ziparchive->addFile($file_name, $name_in_archive)) {

                            return false;
                    }
                }
                break;
        }

        return true;
    }

    public function close()
    {
        if ($this->ziparchive instanceof \ZipArchive) {
            $this->ziparchive->close();
            $this->ziparchive = null;
        }

        if (!is_resource($this->filehandler)) {
            return;
        }

        // Write tar file end.
        if (in_array($this->method, ['Tar', 'TarGz'], true)) {
            $this->fwrite(pack('a1024', ''));
        }

        $this->fclose();
    }
    public function get_folders_to_backup()
    {

    }

    public function get_method()
    {
        return $this->method;
    }

    private function fopen($filename, $mode)
    {
        $fd = fopen($filename, $mode);

        if (!$fd) {
            return null;
        }

        return $fd;
    }

}