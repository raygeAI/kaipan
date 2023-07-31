<?php

function showProgress($callback,$title='导入项目'){
    @ini_set("output_buffering", "Off");
    @ini_set('zlib.output_compression', 0);
    set_time_limit(0);
    ob_end_clean();
    include template('project/import_data', TEMPLATE_INCLUDEPATH);
    flushData();

    $callback();
    ob_end_flush();
    exit();
    
}

function updateProgress($value, $msg)
{
    echo "<script type='text/javascript'>updateProgress({$value},'{$msg}');</script>";
    flushData();
    if($value==100){
        flushData();
    }
}

function showLog($log)
{
    echo "<script type='text/javascript'>showLog('{$log}');</script>";
    flushData();
}

function flushData()
{
    echo str_pad(' ', 4096);
    @ob_flush();
    flush();
}
