<?php
class S3Testing_JobType_File extends S3Testing_JobTypes
{
    public function __construct()
    {
        $this->info['ID'] = 'FILE';
        $this->info['name'] = 'Files';
        $this->info['description'] = 'File backup';
        $this->info['URI'] = 'http://backwpup.com';
        $this->info['author'] = 'WP Media';
        $this->info['authorURI'] = 'https://wp-media.me';
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
            'fileexclude'              => '',
            'dirinclude'               => '',
            'backupabsfolderup'        => false,
        ];
    }


}