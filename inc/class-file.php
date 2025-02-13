<?php

class S3Testing_File
{
    public static function check_folder(string $folder, bool $donotbackup = false)
    {
        $folder = self::get_absolute_path($folder);
        $folder = untrailingslashit($folder);

        $foldersToProcess = [$folder];
        $parentFolder = dirname($folder);

        while (!file_exists($parentFolder)) {
            array_unshift($foldersToProcess, $parentFolder);
            $parentFolder = dirname($parentFolder);
        }

        foreach ($foldersToProcess as $childFolder) {
            if (!is_dir($childFolder) && !wp_mkdir_p($childFolder)) {
                return sprintf(__('Cannot create folder: %1$s'), $childFolder);
            }
            if (!is_writable($childFolder)) {
                return sprintf(__('Folder "%1$s" is not writable'), $childFolder);
            }
            if ($donotbackup) {
                self::write_do_not_backup_file($childFolder);
            }
        }

        return '';
    }

    private static function write_do_not_backup_file($folder)
    {
        $doNotBackupFile = "{$folder}/.donotbackup";

        if (!file_exists($doNotBackupFile)) {
            file_put_contents(
                $doNotBackupFile,
                __(
                    'S3Testing will not backup folders and its sub folders when this file is inside.'
                )
            );
        }
    }

    public static function get_absolute_path($path = '/')
    {
        $path = S3Testing_Path_Fixer::slashify($path);
        $content_path = trailingslashit(S3Testing_Path_Fixer::slashify((string) WP_CONTENT_DIR));

        if (empty($path) || $path === '/') {
            $path = $content_path;
        }

        if (substr($path, 0, 1) !== '/' && !preg_match('#^[a-zA-Z]+:/#', $path)) {
            $path = $content_path . $path;
        }

        return self::resolve_path($path);
    }

    protected static function resolve_path($path)
    {
        $parts = explode('/', $path);
        $resolvedParts = [];

        foreach ($parts as $part) {
            if ($part === '..') {
                if (!empty($resolvedParts)) {
                    array_pop($resolvedParts);
                }
            } elseif ($part === '.') {
                continue;
            } else {
                $resolvedParts[] = $part;
            }
        }

        return implode('/', $resolvedParts);
    }
}