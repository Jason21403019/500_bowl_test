<?php
// 引入 CORS 標頭設置
require_once 'cors.php';
require_once 'timezone.php';
require_once 'redis.php';

// 設置返回 JSON 內容類型
header('Content-Type: application/json');

function createDemoFoods($redis)
{
    $foods = [
        [
            'id' => '1',
            'votes' => '0',
            'title' => '牛肉麵',
            'tags' => '麵食類',
            'image' => '/image/food/1.png',
        ],
        [
            'id' => '2',
            'votes' => '0',
            'title' => '滷肉飯',
            'tags' => '米飯類',
            'image' => '/image/food/2.png',
        ],
        [
            'id' => '3',
            'votes' => '0',
            'title' => '蚵仔煎',
            'tags' => '海鮮類',
            'image' => '/image/food/3.png',
        ],
        [
            'id' => '4',
            'votes' => '0',
            'title' => '小籠包',
            'tags' => '包子類',
            'image' => '/image/food/4.png',
        ],
        [
            'id' => '5',
            'votes' => '0',
            'title' => '雞排',
            'tags' => '炸物類',
            'image' => '/image/food/5.png',
        ],
        [
            'id' => '6',
            'votes' => '0',
            'title' => '鹽酥雞',
            'tags' => '炸物類',
            'image' => '/image/food/6.png',
        ],
        [
            'id' => '7',
            'votes' => '0',
            'title' => '臭豆腐',
            'tags' => '炸物類',
            'image' => '/image/food/7.png',
        ],
    ];

    $count = 0;
    $result = [];

    try {
        // 清空現有的食物索引和評分排序
        $redis->del('foods:index', 'foods:votes');
          if (!empty($existingFoodIds)) {
            // 刪除每個食物的詳細資料
            foreach ($existingFoodIds as $foodId) {
                $redis->del("food:{$foodId}");
            }
        }
        
        // 清空索引和排序集合
        $redis->del('foods:index');
        $redis->del('foods:votes');

        // 批次新增食物資料
        foreach ($foods as $food) {
            $foodId = $food['id'];
            $key = "food:{$foodId}";

            // 使用 HMSET 設置食物資料
            $redis->hmset($key, $food);

            // 添加到食物索引
            $redis->sadd('foods:index', $foodId);

            // 添加到票數排序集
            $redis->zadd('foods:votes', intval($food['votes']), $foodId);

            $count++;
        }

        $result = [
            'success' => true,
            'message' => "成功創建 {$count} 個示例食物",
            'count' => $count
        ];
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'message' => '創建示例食物失敗: ' . $e->getMessage(),
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

// 執行示例數據創建
$result = createDemoFoods($redis);

// 輸出結果
echo json_encode($result);