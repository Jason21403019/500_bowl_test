<?php
// 引入 CORS 標頭設置
require_once 'cors.php';
require_once 'timezone.php'; // 引入台北時間設定

// 設置返回 JSON 內容類型
header("Content-Type: application/json");

// 引入 Redis 連接工具
require_once 'redis.php';

/**
 * API 響應函數
 * @param boolean $success 請求是否成功
 * @param string $message 訊息
 * @param array $data 數據
 * @param int $status HTTP 狀態碼
 * @return void
 */
function apiResponse($success, $message = '', $data = [], $status = 200)
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * 使用 HSET 儲存食物資訊
 * @param Predis\Client $redis Redis連接實例
 * @param string $foodId 食物ID
 * @param array $foodData 食物資料
 * @return bool 操作是否成功
 */
function storeFoodData($redis, $foodId, $foodData)
{
    $key = "food:{$foodId}";

    // 檢查是否為更新操作
    $isUpdate = $redis->exists($key);

    // 使用 HMSET 一次性設置多個欄位
    $result = $redis->hmset($key, $foodData);

    // 將食物ID添加到索引集合中
    $redis->sadd('foods:index', $foodId);

    // 同步更新 foods:votes 有序集合
    $votes = isset($foodData['votes']) ? intval($foodData['votes']) : 0;
    $redis->zadd('foods:votes', $votes, $foodId);

    return $result === true;
}

/**
 * 同步食物投票數到foods:votes有序集合
 * @param Predis\Client $redis Redis連接實例
 * @param string $foodId 食物ID
 * @return int 食物的當前投票數
 */
function syncFoodVotes($redis, $foodId)
{
    $foodKey = "food:{$foodId}";
    $votes = (int)$redis->hget($foodKey, 'votes');
    $redis->zadd('foods:votes', $votes, $foodId);
    return $votes;
}

/**
 * 重建foods:votes有序集合的數據
 * @param Predis\Client $redis Redis連接實例
 * @return int 處理的食物數量
 */
function rebuildFoodsVotesZSet($redis)
{
    // 獲取所有食物ID
    $foodIds = $redis->smembers('foods:index');

    // 如果索引為空，先初始化索引
    if (empty($foodIds)) {
        initializeFoodIndex($redis);
        $foodIds = $redis->smembers('foods:index');
    }

    $count = 0;

    // 對每個食物進行同步 - 使用管道批處理提高性能
    $pipe = $redis->pipeline();
    foreach ($foodIds as $foodId) {
        $foodKey = "food:{$foodId}";
        if ($redis->exists($foodKey)) {
            $votes = (int)$redis->hget($foodKey, 'votes');
            $pipe->zadd('foods:votes', $votes, $foodId);
            $count++;
        }
    }
    $pipe->execute();

    return $count;
}

/**
 * 獲取所有食物資料，使用 Redis 排序集合提高查詢效率
 * @param Predis\Client $redis Redis連接實例
 * @param string|null $tag 標籤過濾
 * @param string|null $sort 排序欄位
 * @param string $order 排序順序 ('asc' 或 'desc')
 * @param int|null $limit 限制數量
 */
function getAllFoods($redis, $tag = null, $sort = null, $order = 'desc', $limit = null)
{
    // 檢查foods:votes是否需要重建
    if ($sort === 'votes' && !$redis->exists('foods:votes')) {
        rebuildFoodsVotesZSet($redis);
    }

    // 根據排序類型獲取食物ID
    $foodIds = [];
    if ($sort === 'votes' && $redis->exists('foods:votes')) {
        // 使用 Redis 排序集合，提高查詢效率
        $foodIds = $order === 'asc'
            ? $redis->zrange('foods:votes', 0, -1)
            : $redis->zrevrange('foods:votes', 0, -1);
    } else {
        $foodIds = $redis->smembers('foods:index');
    }

    // 如果沒有食物ID，進行初始化
    if (empty($foodIds)) {
        initializeFoodIndex($redis);
        $foodIds = $redis->smembers('foods:index');

        if ($sort === 'votes') {
            initializeVotesZSet($redis, $foodIds);
            $foodIds = $order === 'asc'
                ? $redis->zrange('foods:votes', 0, -1)
                : $redis->zrevrange('foods:votes', 0, -1);
        }
    }

    // 優化：如果指定了限制數量，先限制要處理的ID數量
    if ($limit !== null && is_numeric($limit) && $limit > 0 && !$tag) {
        $foodIds = array_slice($foodIds, 0, $limit);
    }

    // 使用管道批量獲取食物數據，減少網絡往返
    $pipe = $redis->pipeline();
    foreach ($foodIds as $foodId) {
        $pipe->hgetall("food:{$foodId}");
    }
    $foodsData = $pipe->execute();

    // 篩選和格式化結果
    $filteredFoods = [];
    foreach ($foodsData as $index => $foodData) {
        if (empty($foodData)) {
            continue;
        }

        $foodId = $foodIds[$index];

        // 處理標籤 - 保持單一標籤格式
        if (isset($foodData['tags']) && is_string($foodData['tags'])) {
            // 如果包含逗號，只取第一個標籤
            if (strpos($foodData['tags'], ',') !== false) {
                $tagArray = explode(',', $foodData['tags']);
                $foodData['tags'] = trim($tagArray[0]);
            }
            // 如果是單一標籤，保持不變
        } else {
            $foodData['tags'] = '';
        }

        // 標籤過濾
        if ($tag && !in_array($tag, $foodData['tags'])) {
            continue;
        }

        // 轉換數值類型
        if (isset($foodData['votes'])) {
            $foodData['votes'] = (int)$foodData['votes'];
        }
        if (isset($foodData['id'])) {
            $foodData['id'] = (int)$foodData['id'];
        }

        $filteredFoods[] = $foodData;
    }

    // 如果不使用 Redis 排序或需要進一步根據標籤過濾後排序
    if ($tag && $sort === 'votes') {
        usort($filteredFoods, function ($a, $b) use ($order) {
            return $order === 'asc'
                ? $a['votes'] - $b['votes']
                : $b['votes'] - $a['votes'];
        });
    }

    // 如果有限制數量且之前未應用（由於標籤過濾），再次應用
    if ($limit !== null && is_numeric($limit) && $limit > 0 && $tag) {
        $filteredFoods = array_slice($filteredFoods, 0, $limit);
    }

    return $filteredFoods;
}

/**
 * 初始化食物索引
 * @param Predis\Client $redis Redis連接實例
 */
function initializeFoodIndex($redis)
{
    $cursor = '0';
    $pipe = $redis->pipeline();
    $count = 0;

    do {
        list($cursor, $keys) = $redis->scan($cursor, ['match' => 'food:*', 'count' => 100]);

        foreach ($keys as $key) {
            // 從 "food:123" 格式中提取 "123" 作為 ID
            $foodId = substr($key, 5);
            $pipe->sadd('foods:index', $foodId);
            $count++;

            // 每積累100個操作執行一次，避免管道過大
            if ($count % 100 === 0) {
                $pipe->execute();
                $pipe = $redis->pipeline();
            }
        }
    } while ($cursor != '0');

    // 執行剩餘操作
    if ($count % 100 !== 0) {
        $pipe->execute();
    }
}

/**
 * 初始化投票排序的有序集合
 * @param Predis\Client $redis Redis連接實例
 * @param array $foodIds 食物ID數組
 */
function initializeVotesZSet($redis, $foodIds)
{
    $pipe = $redis->pipeline();
    $count = 0;

    foreach ($foodIds as $foodId) {
        $key = "food:{$foodId}";
        $votes = $redis->hget($key, 'votes');
        $votes = $votes ? intval($votes) : 0;
        $pipe->zadd('foods:votes', $votes, $foodId);
        $count++;

        // 每積累100個操作執行一次，避免管道過大
        if ($count % 100 === 0) {
            $pipe->execute();
            $pipe = $redis->pipeline();
        }
    }

    // 執行剩餘操作
    if ($count % 100 !== 0) {
        $pipe->execute();
    }
}

/**
 * 獲取單個食物的詳細數據
 * @param Predis\Client $redis Redis連接實例
 * @param string $foodId 食物ID
 * @return array|null 食物數據，如果食物不存在則返回null
 */
function getFoodDetail($redis, $foodId)
{
    $key = "food:{$foodId}";

    // 檢查鍵是否存在
    if (!$redis->exists($key)) {
        return null;
    }

    // 使用管道一次性獲取所有數據，減少網絡往返
    $foodData = $redis->hgetall($key);

    // 格式化食物數據 - 保持單一標籤格式
    if (isset($foodData['tags']) && is_string($foodData['tags'])) {
        // 如果包含逗號，只取第一個標籤
        if (strpos($foodData['tags'], ',') !== false) {
            $tagArray = explode(',', $foodData['tags']);
            $foodData['tags'] = trim($tagArray[0]);
        }
        // 如果是單一標籤，保持不變
    } else {
        $foodData['tags'] = '';
    }

    if (isset($foodData['votes'])) {
        $foodData['votes'] = (int)$foodData['votes'];
    }

    if (isset($foodData['id'])) {
        $foodData['id'] = (int)$foodData['id'];
    }

    // 構建回應數據結構
    $result = [
        'food_data' => $foodData,
        'hash_analysis' => [
            'key' => $key,
            'field_count' => count($foodData),
            'fields' => []
        ]
    ];

    // 構建 fields 分析數據
    $counter = 1;
    foreach ($foodData as $field => $value) {
        $result['hash_analysis']['fields'][] = [
            'number' => $counter++,
            'field' => $field,
            'value' => $value
        ];
    }

    return $result;
}

// 主程式
$redis = getRedisConnection(true);
if (!$redis) {
    apiResponse(false, "無法連接到 Redis 服務器，請檢查連接設定。", [], 500);
}

// 處理 GET 請求 - 獲取食物資料
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 設置禁用快取的標頭
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // 處理重建foods:votes的特殊請求
    if (isset($_GET['rebuild_votes_zset']) && $_GET['rebuild_votes_zset'] === 'true') {
        $count = rebuildFoodsVotesZSet($redis);
        apiResponse(true, "成功重建foods:votes有序集合", ['processed_count' => $count]);
    }

    // 檢查是否請求特定食物的詳細信息
    if (isset($_GET['id'])) {
        $foodId = $_GET['id'];
        $foodDetail = getFoodDetail($redis, $foodId);

        if ($foodDetail) {
            apiResponse(true, "獲取食物詳細信息成功", $foodDetail);
        } else {
            apiResponse(false, "找不到指定的食物", [], 404);
        }
    } else {
        $tag = isset($_GET['tag']) ? $_GET['tag'] : null;
        $sort = isset($_GET['sort']) ? $_GET['sort'] : null;
        $order = isset($_GET['order']) ? $_GET['order'] : 'desc';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

        // 使用優化過的 getAllFoods 函數
        $foods = getAllFoods($redis, $tag, $sort, $order, $limit);

        apiResponse(true, "獲取食物列表成功", ['foods' => $foods]);
    }
}
// 處理 POST 請求 - 添加新食物
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 獲取請求內容
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['id']) || !isset($data['title'])) {
        apiResponse(false, "無效的食物資料格式", [], 400);
    }
    // 確保標籤只有一個
    if (isset($data['tags'])) {
        if (is_array($data['tags'])) {
            // 如果是陣列，只取第一個元素
            $data['tags'] = $data['tags'][0];
        } elseif (is_string($data['tags']) && strpos($data['tags'], ',') !== false) {
            // 如果是逗號分隔的字串，只取第一個
            $tagArray = explode(',', $data['tags']);
            $data['tags'] = trim($tagArray[0]);
        }
    }

    // 存儲食物資料
    $result = storeFoodData($redis, $data['id'], $data);

    if ($result) {
        apiResponse(true, "食物新增成功", $data);
    } else {
        apiResponse(false, "食物新增失敗", [], 500);
    }
}
// 處理 OPTIONS 請求 (CORS 預檢請求)
else if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    apiResponse(true, "");
}
// 不支援的請求方法
else {
    apiResponse(false, "不支援的請求方法", [], 405);
}