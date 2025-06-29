<?php

// 引入 Composer 自動加載
require_once 'vendor/autoload.php';

/**
 * 獲取 Redis 連接實例
 * @param bool $silent 是否靜默模式（不輸出連接訊息）
 * @return Predis\Client|null Redis 連接實例，連接失敗時返回 null
 */
function getRedisConnection($silent = false)
{
    // Redis 連接設置
    $redis_host = '10.18.1.96';  // Redis 服務器地址
    $redis_port = 6379;         // Redis 端口
    $redis_password = 'm309ndvh';     // Redis 密碼，如果沒有設置則為 null
    $redis_database = 9;        // 使用 Redis 資料庫 9

    // 創建連接
    try {
        $redis = new Predis\Client([
            'scheme' => 'tcp',
            'host'   => $redis_host,
            'port'   => $redis_port,
            'password' => $redis_password,
            'database' => $redis_database,
            'read_write_timeout' => 0,
        ]);

        // 驗證連接
        $redis->ping();

        if (!$silent) {
            echo "Redis 連接成功！<br>";
        }

        return $redis;
    } catch (Exception $e) {
        if (!$silent) {
            echo "Redis 連接失敗: " . $e->getMessage() . "<br>";
        }
        return null;
    }
}

/**
 * 檢查 Redis 連接狀態
 * @param bool $returnArray 是否以數組形式返回結果
 * @return bool|array 連接成功返回 true 或包含連接信息的數組，失敗返回 false 或包含錯誤信息的數組
 */
function checkRedisConnection($returnArray = false)
{
    try {
        $redis = getRedisConnection(true);

        if ($redis === null) {
            if ($returnArray) {
                return [
                    'status' => false,
                    'message' => '無法建立 Redis 連接'
                ];
            }
            return false;
        }

        // 嘗試執行一個簡單命令來驗證連接
        $pong = $redis->ping();

        if ($pong == 'PONG') {
            if ($returnArray) {
                return [
                    'status' => true,
                    'message' => 'Redis 連接成功',
                    'info' => $redis->info()
                ];
            }
            return true;
        } else {
            if ($returnArray) {
                return [
                    'status' => false,
                    'message' => 'Redis 服務響應異常'
                ];
            }
            return false;
        }
    } catch (Exception $e) {
        if ($returnArray) {
            return [
                'status' => false,
                'message' => $e->getMessage()
            ];
        }
        return false;
    }
}

function sanitizeRedisKey($key) {
    // 移除可能危險的字符
    $key = preg_replace('/[^a-zA-Z0-9:_-]/', '', $key);
    
    // 限制鍵名長度
    if (strlen($key) > 250) {
        throw new InvalidArgumentException("Redis 鍵名過長");
    }
    
    return $key;
}

function validateFoodId($foodId) {
    // 確保 food ID 是有效的數字
    if (!is_numeric($foodId)) {
        throw new InvalidArgumentException("無效的食物ID格式");
    }
    
    $id = intval($foodId);
    if ($id < 1 || $id > 125) {
        throw new InvalidArgumentException("食物ID超出有效範圍");
    }
    
    return $id;
}