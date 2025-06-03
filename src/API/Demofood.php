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
            'tags' => '麵食類,湯麵',
            'image' => '/image/food/1.png',
        ],
        [
            'id' => '2',
            'votes' => '0',
            'title' => '滷肉飯',
            'tags' => '米飯類,國民美食',
            'image' => '/image/food/2.png',
        ],
        [
            'id' => '3',
            'votes' => '0',
            'title' => '蚵仔煎',
            'tags' => '海鮮類,夜市美食',
            'image' => '/image/food/3.png',
        ],
        [
            'id' => '4',
            'votes' => '0',
            'title' => '小籠包',
            'tags' => '包子類,蒸食',
            'image' => '/image/food/4.png',
        ],
        [
            'id' => '5',
            'votes' => '0',
            'title' => '雞排',
            'tags' => '炸物類,夜市美食',
            'image' => '/image/food/5.png',
        ],
        [
            'id' => '6',
            'votes' => '0',
            'title' => '鹽酥雞',
            'tags' => '炸物類,夜市美食',
            'image' => '/image/food/6.png',
        ],
        [
            'id' => '7',
            'votes' => '0',
            'title' => '臭豆腐',
            'tags' => '炸物類,小吃',
            'image' => '/image/food/7.png',
        ],
        // [
        //     'id' => '8',
        //     'votes' => '0',
        //     'title' => '珍珠奶茶',
        //     'tags' => '甜品類,飲料',
        //     'image' => '/image/food/8.png',
        // ],
        // [
        //     'id' => '9',
        //     'votes' => '0',
        //     'title' => '豆花',
        //     'tags' => '甜品類,傳統',
        //     'image' => '/image/food/9.png',
        // ],
        // [
        //     'id' => '10',
        //     'votes' => '0',
        //     'title' => '蔥油餅',
        //     'tags' => '麵餅類,街頭小吃',
        //     'image' => '/image/food/10.png',
        // ],
        // [
        //     'id' => '11',
        //     'votes' => '0',
        //     'title' => '肉圓',
        //     'tags' => '米食類,傳統',
        //     'image' => '/image/food/food11.png',
        // ],
        // [
        //     'id' => '12',
        //     'votes' => '0',
        //     'title' => '水餃',
        //     'tags' => '餃子類,麵食',
        //     'image' => '/image/food/food12.png',
        // ],
        // [
        //     'id' => '13',
        //     'votes' => '0',
        //     'title' => '燒烤串',
        //     'tags' => '燒烤類,夜市美食',
        //     'image' => '/image/food/food13.png',
        // ],
        // [
        //     'id' => '14',
        //     'votes' => '0',
        //     'title' => '魯肉飯',
        //     'tags' => '米飯類,傳統',
        //     'image' => '/image/food/food14.png',
        // ],
        // [
        //     'id' => '15',
        //     'votes' => '0',
        //     'title' => '蝦捲',
        //     'tags' => '海鮮類,炸物類',
        //     'image' => '/image/food/food15.png',
        // ],
        // [
        //     'id' => '16',
        //     'votes' => '0',
        //     'title' => '紅燒牛肉麵',
        //     'tags' => '麵食類,湯麵',
        //     'image' => '/image/food/food16.png',
        // ],
        // [
        //     'id' => '17',
        //     'votes' => '0',
        //     'title' => '清燉牛肉麵',
        //     'tags' => '麵食類,湯麵',
        //     'image' => '/image/food/food17.png',
        // ],
        // [
        //     'id' => '18',
        //     'votes' => '0',
        //     'title' => '蘿蔔糕',
        //     'tags' => '米食類,早餐',
        //     'image' => '/image/food/food18.png',
        // ],
        // [
        //     'id' => '19',
        //     'votes' => '0',
        //     'title' => '麻辣鍋',
        //     'tags' => '羹湯類,四川料理',
        //     'image' => '/image/food/food19.png',
        // ],
        // [
        //     'id' => '20',
        //     'votes' => '0',
        //     'title' => '陽春麵',
        //     'tags' => '麵食類,簡餐',
        //     'image' => '/image/food/food20.png',
        // ],
        // [
        //     'id' => '21',
        //     'votes' => '0',
        //     'title' => '蔥爆牛肉',
        //     'tags' => '肉品類,熱炒',
        //     'image' => '/image/food/food21.png',
        // ],
        // [
        //     'id' => '22',
        //     'votes' => '0',
        //     'title' => '涼拌小黃瓜',
        //     'tags' => '小菜類,開胃菜',
        //     'image' => '/image/food/food22.png',
        // ],
        // [
        //     'id' => '23',
        //     'votes' => '0',
        //     'title' => '皮蛋豆腐',
        //     'tags' => '小菜類,傳統',
        //     'image' => '/image/food/food23.png',
        // ],
        // [
        //     'id' => '24',
        //     'votes' => '0',
        //     'title' => '豬血湯',
        //     'tags' => '羹湯類,傳統',
        //     'image' => '/image/food/food24.png',
        // ],
        // [
        //     'id' => '25',
        //     'votes' => '0',
        //     'title' => '蚵仔湯',
        //     'tags' => '羹湯類,海鮮類',
        //     'image' => '/image/food/food25.png',
        // ],
        // [
        //     'id' => '26',
        //     'votes' => '0',
        //     'title' => '蒜泥白肉',
        //     'tags' => '肉品類,涼菜',
        //     'image' => '/image/food/food26.png',
        // ],
        // [
        //     'id' => '27',
        //     'votes' => '0',
        //     'title' => '薑母鴨',
        //     'tags' => '羹湯類,冬季美食',
        //     'image' => '/image/food/food27.png',
        // ],
        // [
        //     'id' => '28',
        //     'votes' => '0',
        //     'title' => '炒飯',
        //     'tags' => '米飯類,快炒',
        //     'image' => '/image/food/food28.png',
        // ],
        // [
        //     'id' => '29',
        //     'votes' => '0',
        //     'title' => '蛋炒飯',
        //     'tags' => '米飯類,家常菜',
        //     'image' => '/image/food/food29.png',
        // ],
        // [
        //     'id' => '30',
        //     'votes' => '0',
        //     'title' => '鍋貼',
        //     'tags' => '餃子類,煎餃',
        //     'image' => '/image/food/food30.png',
        // ]
    ];

    $count = 0;
    $result = [];

    try {
        // 清空現有的食物索引和評分排序
        $redis->del('foods:index', 'foods:votes');

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