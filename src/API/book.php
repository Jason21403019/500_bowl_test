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
 * 使用 HSET 儲存書籍資訊
 * @param Predis\Client $redis Redis連接實例
 * @param string $bookId 書籍ID
 * @param array $bookData 書籍資料
 * @return bool 操作是否成功
 */
function storeBookData($redis, $bookId, $bookData)
{
    $key = "book:{$bookId}";

    // 檢查是否為更新操作
    $isUpdate = $redis->exists($key);

    // 使用 HMSET 一次性設置多個欄位
    $result = $redis->hmset($key, $bookData);

    // 將書籍ID添加到索引集合中
    $redis->sadd('books:index', $bookId);

    // 同步更新 books:votes 有序集合
    $votes = isset($bookData['votes']) ? intval($bookData['votes']) : 0;
    $redis->zadd('books:votes', $votes, $bookId);

    return $result === true;
}

/**
 * 同步書籍投票數到books:votes有序集合
 * @param Predis\Client $redis Redis連接實例
 * @param string $bookId 書籍ID
 * @return int 書籍的當前投票數
 */
function syncBookVotes($redis, $bookId)
{
    $bookKey = "book:{$bookId}";
    $votes = (int)$redis->hget($bookKey, 'votes');
    $redis->zadd('books:votes', $votes, $bookId);
    return $votes;
}

/**
 * 重建books:votes有序集合的數據
 * @param Predis\Client $redis Redis連接實例
 * @return int 處理的書籍數量
 */
function rebuildBooksVotesZSet($redis)
{
    // 獲取所有書籍ID
    $bookIds = $redis->smembers('books:index');

    // 如果索引為空，先初始化索引
    if (empty($bookIds)) {
        initializeBookIndex($redis);
        $bookIds = $redis->smembers('books:index');
    }

    $count = 0;

    // 對每本書進行同步 - 使用管道批處理提高性能
    $pipe = $redis->pipeline();
    foreach ($bookIds as $bookId) {
        $bookKey = "book:{$bookId}";
        if ($redis->exists($bookKey)) {
            $votes = (int)$redis->hget($bookKey, 'votes');
            $pipe->zadd('books:votes', $votes, $bookId);
            $count++;
        }
    }
    $pipe->execute();

    return $count;
}

/**
 * 獲取所有書籍資料，使用 Redis 排序集合提高查詢效率
 * @param Predis\Client $redis Redis連接實例
 * @param string|null $tag 標籤過濾
 * @param string|null $sort 排序欄位
 * @param string $order 排序順序 ('asc' 或 'desc')
 * @param int|null $limit 限制數量
 */
function getAllBooks($redis, $tag = null, $sort = null, $order = 'desc', $limit = null)
{
    // 檢查books:votes是否需要重建
    if ($sort === 'votes' && !$redis->exists('books:votes')) {
        rebuildBooksVotesZSet($redis);
    }

    // 根據排序類型獲取書籍ID
    $bookIds = [];
    if ($sort === 'votes' && $redis->exists('books:votes')) {
        // 使用 Redis 排序集合，提高查詢效率
        $bookIds = $order === 'asc'
            ? $redis->zrange('books:votes', 0, -1)
            : $redis->zrevrange('books:votes', 0, -1);
    } else {
        $bookIds = $redis->smembers('books:index');
    }

    // 如果沒有書籍ID，進行初始化
    if (empty($bookIds)) {
        initializeBookIndex($redis);
        $bookIds = $redis->smembers('books:index');

        if ($sort === 'votes') {
            initializeVotesZSet($redis, $bookIds);
            $bookIds = $order === 'asc'
                ? $redis->zrange('books:votes', 0, -1)
                : $redis->zrevrange('books:votes', 0, -1);
        }
    }

    // 優化：如果指定了限制數量，先限制要處理的ID數量
    if ($limit !== null && is_numeric($limit) && $limit > 0 && !$tag) {
        $bookIds = array_slice($bookIds, 0, $limit);
    }

    // 使用管道批量獲取書籍數據，減少網絡往返
    $pipe = $redis->pipeline();
    foreach ($bookIds as $bookId) {
        $pipe->hgetall("book:{$bookId}");
    }
    $booksData = $pipe->execute();

    // 篩選和格式化結果
    $filteredBooks = [];
    foreach ($booksData as $index => $bookData) {
        if (empty($bookData)) {
            continue;
        }

        $bookId = $bookIds[$index];

        // 處理標籤
        if (isset($bookData['tags']) && is_string($bookData['tags'])) {
            $bookData['tags'] = explode(',', $bookData['tags']);
        } else {
            $bookData['tags'] = [];
        }

        // 標籤過濾
        if ($tag && !in_array($tag, $bookData['tags'])) {
            continue;
        }

        // 轉換數值類型
        if (isset($bookData['votes'])) {
            $bookData['votes'] = (int)$bookData['votes'];
        }
        if (isset($bookData['id'])) {
            $bookData['id'] = (int)$bookData['id'];
        }

        $filteredBooks[] = $bookData;
    }

    // 如果不使用 Redis 排序或需要進一步根據標籤過濾後排序
    if ($tag && $sort === 'votes') {
        usort($filteredBooks, function ($a, $b) use ($order) {
            return $order === 'asc'
                ? $a['votes'] - $b['votes']
                : $b['votes'] - $a['votes'];
        });
    }

    // 如果有限制數量且之前未應用（由於標籤過濾），再次應用
    if ($limit !== null && is_numeric($limit) && $limit > 0 && $tag) {
        $filteredBooks = array_slice($filteredBooks, 0, $limit);
    }

    return $filteredBooks;
}

/**
 * 初始化書籍索引
 * @param Predis\Client $redis Redis連接實例
 */
function initializeBookIndex($redis)
{
    $cursor = '0';
    $pipe = $redis->pipeline();
    $count = 0;

    do {
        list($cursor, $keys) = $redis->scan($cursor, ['match' => 'book:*', 'count' => 100]);

        foreach ($keys as $key) {
            // 從 "book:123" 格式中提取 "123" 作為 ID
            $bookId = substr($key, 5);
            $pipe->sadd('books:index', $bookId);
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
 * @param array $bookIds 書籍ID數組
 */
function initializeVotesZSet($redis, $bookIds)
{
    $pipe = $redis->pipeline();
    $count = 0;

    foreach ($bookIds as $bookId) {
        $key = "book:{$bookId}";
        $votes = $redis->hget($key, 'votes');
        $votes = $votes ? intval($votes) : 0;
        $pipe->zadd('books:votes', $votes, $bookId);
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
 * 獲取單本書籍的詳細數據
 * @param Predis\Client $redis Redis連接實例
 * @param string $bookId 書籍ID
 * @return array|null 書籍數據，如果書籍不存在則返回null
 */
function getBookDetail($redis, $bookId)
{
    $key = "book:{$bookId}";

    // 檢查鍵是否存在
    if (!$redis->exists($key)) {
        return null;
    }

    // 使用管道一次性獲取所有數據，減少網絡往返
    $bookData = $redis->hgetall($key);

    // 格式化書籍數據
    if (isset($bookData['tags']) && is_string($bookData['tags'])) {
        $bookData['tags'] = explode(',', $bookData['tags']);
    } else {
        $bookData['tags'] = [];
    }

    if (isset($bookData['votes'])) {
        $bookData['votes'] = (int)$bookData['votes'];
    }

    if (isset($bookData['id'])) {
        $bookData['id'] = (int)$bookData['id'];
    }

    // 構建回應數據結構
    $result = [
        'book_data' => $bookData,
        'hash_analysis' => [
            'key' => $key,
            'field_count' => count($bookData),
            'fields' => []
        ]
    ];

    // 構建 fields 分析數據
    $counter = 1;
    foreach ($bookData as $field => $value) {
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

// 處理 GET 請求 - 獲取書籍資料
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 設置禁用快取的標頭
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // 處理重建books:votes的特殊請求
    if (isset($_GET['rebuild_votes_zset']) && $_GET['rebuild_votes_zset'] === 'true') {
        $count = rebuildBooksVotesZSet($redis);
        apiResponse(true, "成功重建books:votes有序集合", ['processed_count' => $count]);
    }

    // 檢查是否請求特定書籍的詳細信息
    if (isset($_GET['id'])) {
        $bookId = $_GET['id'];
        $bookDetail = getBookDetail($redis, $bookId);

        if ($bookDetail) {
            apiResponse(true, "獲取書籍詳細信息成功", $bookDetail);
        } else {
            apiResponse(false, "找不到指定的書籍", [], 404);
        }
    } else {
        $tag = isset($_GET['tag']) ? $_GET['tag'] : null;
        $sort = isset($_GET['sort']) ? $_GET['sort'] : null;
        $order = isset($_GET['order']) ? $_GET['order'] : 'desc';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;

        // 使用優化過的 getAllBooks 函數
        $books = getAllBooks($redis, $tag, $sort, $order, $limit);

        apiResponse(true, "獲取書籍列表成功", ['books' => $books]);
    }
}
// 處理 POST 請求 - 添加新書籍
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 獲取請求內容
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['id']) || !isset($data['title'])) {
        apiResponse(false, "無效的書籍資料格式", [], 400);
    }

    // 將標籤陣列轉為逗號分隔的字串
    if (isset($data['tags']) && is_array($data['tags'])) {
        $data['tags'] = implode(',', $data['tags']);
    }

    // 存儲書籍資料
    $result = storeBookData($redis, $data['id'], $data);

    if ($result) {
        apiResponse(true, "書籍新增成功", $data);
    } else {
        apiResponse(false, "書籍新增失敗", [], 500);
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
