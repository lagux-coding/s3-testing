<?php

class S3Testing_Directory extends DirectoryIterator
{
    public function __construct($path)
    {
        parent::__construct(S3Testing_Path_Fixer::fix_path($path));
    }
}