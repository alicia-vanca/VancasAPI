<?php

// Logs
function markTime()
{
    date_default_timezone_set("Asia/Ho_Chi_Minh");
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/log_' . date("m.Y") . '.log', "\n-------------------------------------------\n", FILE_APPEND);
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/log_' . date("m.Y") . '.log', "⏬ ⏬ ⏬ ⏬ " . date("H:i:s d.m.Y") . " ⏬ ⏬ ⏬ ⏬\n\n", FILE_APPEND);
}

function writeLog($data)
{
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/log_' . date("m.Y") . '.log', "$data\n\n", FILE_APPEND);
}

function renderEmail($contentHeader, $contentBody, $orderItems = null)
{
    ob_start();
    include $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/email-template.phtml';
    return ob_get_contents();
}
