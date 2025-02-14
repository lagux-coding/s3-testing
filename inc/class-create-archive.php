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
            case 'Tar':
                $this->tar_empty_folder($folder_name, $name_in_archive);
                return false;
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
            case 'Tar':
                if (function_exists('iconv') && stripos(PHP_OS, 'win') === 0) {
                    $test = @iconv('ISO-8859-1', 'UTF-8', $name_in_archive);

                    if ($test) {
                        $name_in_archive = $test;
                    }
                }

                return $this->tar_file($file_name, $name_in_archive);
                break;
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

    private function tar_file($file_name, $name_in_archive)
    {
        if (!is_resource($this->filehandler)) {
            return false;
        }

        $chunk_size = 1024 * 1024 * 4;
        $filename = $name_in_archive;
        $filename_prefix = '';


        $filename = $name_in_archive;
        if (100 < strlen($name_in_archive)) {
            $filename_offset = strlen($name_in_archive) - 100;
            $split_pos = strpos($name_in_archive, '/', $filename_offset);

            if ($split_pos === false) {
                $split_pos = strrpos($name_in_archive, '/');
            }

            $filename = substr($name_in_archive, $split_pos + 1);
            $filename_prefix = substr($name_in_archive, 0, $split_pos);

            if (strlen($filename) > 100) {
                $filename = substr($filename, -100);
            }

            if (155 < strlen($filename_prefix)) {

            }
        }
        $file_stat = stat($file_name);
        if (!$file_stat) {
            return true;
        }

        $file_stat['size'] = abs((int) $file_stat['size']);

        // Retrieve owner and group for the file.
        [$owner, $group] = $this->posix_getpwuid($file_stat['uid'], $file_stat['gid']);

        // Generate the TAR header for this file
        $chunk = $this->make_tar_headers(
            $filename,
            $file_stat['mode'],
            $file_stat['uid'],
            $file_stat['gid'],
            $file_stat['size'],
            $file_stat['mtime'],
            0,
            $owner,
            $group,
            $filename_prefix
        );

        $fd = false;
        if ($file_stat['size'] > 0) {
            $fd = fopen($file_name, 'rb');
        }

        if ($fd) {
            // Read/write files in 512 bit Blocks.
            while (($content = fread($fd, 512)) != '') { // phpcs:ignore
                $chunk .= pack('a512', $content);

                if (strlen($chunk) >= $chunk_size) {
                    $this->fwrite($chunk);

                    $chunk = '';
                }
            }
            fclose($fd);
        }

        if (!empty($chunk)) {
            $this->fwrite($chunk);
        }

        return true;
    }

    private function tar_empty_folder($folder_name, $name_in_archive)
    {
        if (!is_resource($this->filehandler)) {
            return false;
        }

        $name_in_archive = trailingslashit($name_in_archive);

        $tar_filename = $name_in_archive;
        $tar_filename_prefix = '';

        // Split filename larger than 100 chars.
        if (100 < strlen($name_in_archive)) {
            $filename_offset = strlen($name_in_archive) - 100;
            $split_pos = strpos($name_in_archive, '/', $filename_offset);

            if ($split_pos === false) {
                $split_pos = strrpos(untrailingslashit($name_in_archive), '/');
            }

            $tar_filename = substr($name_in_archive, $split_pos + 1);
            $tar_filename_prefix = substr($name_in_archive, 0, $split_pos);

            if (strlen($tar_filename) > 100) {
                $tar_filename = substr($tar_filename, -100);
            }

            if (strlen($tar_filename_prefix) > 155) {

            }
        }

        $file_stat = @stat($folder_name);
        // Retrieve owner and group for the file.
        [$owner, $group] = $this->posix_getpwuid($file_stat['uid'], $file_stat['gid']);

        // Generate the TAR header for this file
        $header = $this->make_tar_headers(
            $tar_filename,
            $file_stat['mode'],
            $file_stat['uid'],
            $file_stat['gid'],
            $file_stat['size'],
            $file_stat['mtime'],
            5,
            $owner,
            $group,
            $tar_filename_prefix
        );

        $this->fwrite($header);

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

    private function posix_getpwuid($uid, $gid)
    {
        // Set file user/group name if linux.
        $owner = esc_html__('Unknown');
        $group = esc_html__('Unknown');

        if ( function_exists( 'posix_getpwuid' ) ) {
            $info = posix_getpwuid( $uid );
            if ( $info ) {
                $owner = $info['name'];
            }
            $info = posix_getgrgid( $gid );
            if ( $info ) {
                $group = $info['name'];
            }
        }

        return [
            $owner,
            $group,
        ];
    }

    private function make_tar_headers($name, $mode, $uid, $gid, $size, $mtime, $typeflag, $owner, $group, $prefix)
    {
// Generate the TAR header for this file
        $chunk = pack(
            'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
            $name, //name of file  100
            sprintf('%07o', $mode), //file mode  8
            sprintf('%07o', $uid), //owner user ID  8
            sprintf('%07o', $gid), //owner group ID  8
            sprintf('%011o', $size), //length of file in bytes  12
            sprintf('%011o', $mtime), //modify time of file  12
            '        ', //checksum for header  8
            $typeflag, //type of file  0 or null = File, 5=Dir
            '', //name of linked file  100
            'ustar', //USTAR indicator  6
            '00', //USTAR version  2
            $owner, //owner user name 32
            $group, //owner group name 32
            '', //device major number 8
            '', //device minor number 8
            $prefix, //prefix for file name 155
            ''
        ); //fill block 12

        // Computes the unsigned Checksum of a file's header
        $checksum = 0;

        for ($i = 0; $i < 512; ++$i) {
            $checksum += ord(substr($chunk, $i, 1));
        }

        $checksum = pack('a8', sprintf('%07o', $checksum));

        return substr_replace($chunk, $checksum, 148, 8);
    }

    private function fopen($filename, $mode)
    {
        $fd = fopen($filename, $mode);

        if (!$fd) {
            return null;
        }

        return $fd;
    }

    private function fclose()
    {
        fclose($this->filehandler);
    }

    private function fwrite($content)
    {
//        switch ($this->handlertype) {
//            case 'bz':
//                $content = bzcompress($content);
//                break;
//
//            case 'gz':
//                $content = gzencode($content);
//                break;
//
//            default:
//                break;
//        }

        return (int) fwrite($this->filehandler, $content);
    }
}