<?php

abstract class S3Testing_JobTypes
{
    public $info = [];

    abstract public function __construct();

    abstract public function option_defaults();

    public function creates_file()
    {
        return false;
    }

    abstract public function edit_tab($jobid);

    abstract public function edit_form_post_save($jobid);

    abstract public function job_run(S3Testing_Job $job_object);
}