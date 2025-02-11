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

    }

    private function show_folder($id, $jobid, $path)
    {
        $folder = realpath(S3Testing_Path_Fixer::fix_path($path));

        if ($folder) {
            $folder = untrailingslashit(str_replace('\\', '/', $folder));
        }
        ?>
        <input class="checkbox"
               type="checkbox"
               name="backup<?php echo esc_attr($id); ?>"
               id="idbackup<?php echo esc_attr($id); ?>"
               value="1"/>
        <code title="<?php echo esc_attr(sprintf(__('Path as set by user (symlink?): %s'), $path)); ?>"><?php echo esc_attr($folder); ?></code>
        <?php
    }
}