<?php
// filepath: c:\Users\1\Documents\bd_500bowls_vote2025\src\API\ForceClearFoods.php
require_once 'cors.php';
require_once 'redis.php';

header('Content-Type: application/json');

$redis = getRedisConnection(true);
if (!$redis) {
    echo json_encode(['success' => false, 'message' => '無法連接 Redis']);
    exit;
}

// 強制清除所有食物相關資料
$pattern = 'food:*';
$cursor = '0';
$deletedCount = 0;

do {
    list($cursor, $keys) = $redis->scan($cursor, ['match' => $pattern, 'count' => 100]);
    if (!empty($keys)) {
        $redis->del($keys);
        $deletedCount += count($keys);
    }
} while ($cursor != '0');

// 清除索引和排序
$redis->del('foods:index');
$redis->del('foods:votes');

echo json_encode([
    'success' => true,
    'message' => "強制清除完成",
    'deleted_keys' => $deletedCount
]);