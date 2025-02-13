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
            throw new BackWPup_Create_Archive_Exception(
                __('The file name of an archive cannot be empty.')
            );
        }

        // Check folder can used.
        if (!is_dir(dirname($file)) || !is_writable(dirname($file))) {
            throw new BackWPup_Create_Archive_Exception(
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
                throw new BackWPup_Create_Archive_Exception(
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
                throw new BackWPup_Create_Archive_Exception(
                    esc_html__('Functions for gz compression not available')
                );
            }

            if (\ZipArchive::class === $this->method) {
                $this->ziparchive = new ZipArchive();
                $ziparchive_open = $this->ziparchive->open($this->file, ZipArchive::CREATE);

                if ($ziparchive_open !== true) {
                    $this->ziparchive_status();

                    throw new BackWPup_Create_Archive_Exception(
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
                throw new BackWPup_Create_Archive_Exception(
                    __('Functions for gz compression not available')
                );
            }

            $this->method = 'gz';
            $this->handlertype = 'gz';
            $this->filehandler = $this->fopen($this->file, 'w');
        }

        if ('' === $this->method) {
            throw new BackWPup_Create_Archive_Exception(
                sprintf(
                // translators: the $1 is the type of the archive file
                    esc_html_x('Method to archive file %s not detected', '%s = file name'),
                    basename($this->file)
                )
            );
        }

        if (null === $this->filehandler) {
            throw new BackWPup_Create_Archive_Exception(__('Cannot open archive file'));
        }
    }
    public function get_folders_to_backup()
    {

    }

}