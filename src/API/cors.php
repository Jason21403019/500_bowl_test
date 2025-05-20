<?php

/**
 * 設置 CORS 標頭
 * 可在所有 API 文件中引入此檔案以統一 CORS 標頭
 */
function setCorsHeaders()
{
    // 允許所有來源訪問 (在生產環境中可能需要限制)
    header("Access-Control-Allow-Origin: *");

    // 允許的請求方法
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    // 允許的請求標頭
    header("Access-Control-Allow-Headers: Content-Type, X-User-ID");

    // 處理 OPTIONS 請求（預檢請求）
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// 自動調用函數設置標頭
setCorsHeaders();

// 啟用錯誤日誌
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/api_errors.log');

// 記錄請求信息，幫助調試
$requestInfo = [
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'user_id' => $_SERVER['HTTP_X_USER_ID'] ?? 'Not provided'
];

error_log("API請求: " . json_encode($requestInfo));
