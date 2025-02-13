<?php
class S3Testing_Path_Fixer
{
    public static function fix_path($path)
    {
        if(is_dir($path . 'wp-content')){
            return $path . 'wp-content/..';
        }
        return $path;
    }

    public static function slashify($path)
    {
        return str_replace('\\', '/', (string) $path);
    }
}