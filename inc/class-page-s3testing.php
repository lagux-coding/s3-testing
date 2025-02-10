<?php
class S3Testing_Page_S3Testing
{
    public static function page()
    {
        ?>
            <div class="wrap">
                <h1>
                    <?php
                        echo sprintf('%s &rsaquo; Dashboard', S3Testing::get_plugin_data('name'));
                    ?>
                </h1>
            </div>
        <?php
    }
}