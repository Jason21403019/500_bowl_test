<?php
// 引入 CORS 標頭設置
require_once 'cors.php';
require_once 'timezone.php';
require_once 'redis.php';

// 設置返回 JSON 內容類型
header('Content-Type: application/json');

function createDemoPins($redis, $loadAll = true)
{
    $pins = [];

    // 從 Upoint.json 讀取 pin_code，依 $loadAll 決定載入全部或前 50 筆
    $jsonPath = __DIR__ . '/Upoint.json';
    $jsonData = json_decode(file_get_contents($jsonPath), true);
    $pinList = $loadAll ? $jsonData['PIN'] : array_slice($jsonData['PIN'], 0, 50);

    foreach ($pinList as $i => $item) {
        $discountCode = isset($item['discountCode']) ? $item['discountCode'] : $item['pin_code'];
        $code = $item['pin_code'];
        $pins[] = [
            'id' => (string)($i + 1),
            'discount_code' => $discountCode,
            'pin_code' => $code,
            'created_at' => date('Y-m-d H:i:s', strtotime("-" . (count($pinList) - $i) . " days")),
            'is_used' => '0',
            'used_at' => null
        ];
    }

    $count = 0;
    $result = [];

    try {
        // 清空現有資料
        $redis->del('pins:index');
        $redis->del('pins:used');
        $redis->del('pins:available');

        // 批次新增折扣碼與PIN碼資料
        foreach ($pins as $pin) {
            $pinId = $pin['id'];
            $key = "pin:{$pinId}";

            // 使用 HMSET 設置資料
            $redis->hmset($key, $pin);

            // 添加到索引
            $redis->sadd('pins:index', $pinId);

            // 全部添加到可用集合中
            $redis->sadd('pins:available', $pinId);

            $count++;
        }

        $result = [
            'success' => true,
            'message' => "成功創建 {$count} 組折扣碼與PIN碼",
            'count' => $count,
            'data' => $pins
        ];
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'message' => '創建折扣碼與PIN碼失敗: ' . $e->getMessage(),
            'count' => $count
        ];
    }

    return $result;
}

// 主程式邏輯
$redis = getRedisConnection(true);
if (!$redis) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '無法連接到 Redis 服務器'
    ]);
    exit;
}

// 若沒有特別指定 all 參數，預設將全部加載
$loadAll = !isset($_GET['all']) || $_GET['all'] == '1';
$result = createDemoPins($redis, $loadAll);

// 輸出結果
echo json_encode($result);
