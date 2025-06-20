<?php

// 引入CORS設置和Redis連接
require_once 'cors.php';
require_once 'redis.php';
require_once 'timezone.php'; // 引入台北時間設定

// 設置內容類型為JSON
header('Content-Type: application/json; charset=utf-8');

/**
 * XSS 預防函數 - 過濾字串中的特殊字符
 * @param mixed $data 需要過濾的數據
 * @return mixed 過濾後的數據
 */
function preventXSS($data)
{
    if (is_string($data)) {
        // 對字串進行 HTML 實體編碼
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif (is_array($data)) {
        // 遞歸處理陣列
        foreach ($data as $key => $value) {
            $data[$key] = preventXSS($value);
        }
    }
    return $data;
}

// 獲取請求內容
$requestBody = file_get_contents('php://input');
$data = json_decode($requestBody, true);

// 對輸入的數據進行 XSS 預防處理
if (is_array($data)) {
    $data = preventXSS($data);
}

// 記錄原始請求 - 增加調試資訊
$requestMethod = $_SERVER['REQUEST_METHOD'];
$userAgentInfo = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Unknown';

// 會員驗證 - 使用 chkmember.php 進行驗證
ob_start();
include_once 'chkmember.php';
ob_end_clean();

// 從 chkmember.php 獲取會員資訊 - 使用會員帳號作為 userId
$userId = $is_valid ? preventXSS($account) : null;
$userEmail = $is_valid ? preventXSS($email) : null;
$userIp = preventXSS($ip); // 從 chkmember.php 獲取用戶 IP

// 定義全域變數
$today = getTaipeiTime('Y-m-d');
$maxDailyVotes = 1; // 每日投票上限
$currentTime = time();
$currentDatetime = getTaipeiTime('Y-m-d H:i:s');

// 記錄請求 - 使用 JSON_UNESCAPED_UNICODE 確保正確編碼中文
error_log("Vote API請求: " . json_encode([
    'method' => $requestMethod,
    'data' => $data,
    'user_id' => $userId,
    'user_email' => $userEmail,
    'user_ip' => $userIp,
    'user_agent' => $userAgentInfo,
    'is_valid' => $is_valid
], JSON_UNESCAPED_UNICODE));

// 檢查會員是否有效
if (!$is_valid || !$userId) {
    sendJsonResponse(false, "請先登入會員", null, 401);
    exit;
}

// 獲取Redis連接
$redis = getRedisConnection(true);
if (!$redis) {
    sendJsonResponse(false, "無法連接到Redis服務", null, 500);
    exit;
}

// 確保會員資料存在 - 這部分應該已經在 chkmember.php 中處理
$memberKey = "member:{$userId}";
if (!$redis->exists($memberKey)) {
    // 使用 chkmember.php 中的 saveOrUpdateMember 函數
    if (!saveOrUpdateMember($userId, $userEmail, $userIp)) {
        sendJsonResponse(false, "無法建立會員資料", null, 500);
        exit;
    }
}

// 根據請求方法和action參數處理不同的操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 檢查必要參數
    if (empty($data['action'])) {
        sendJsonResponse(false, "缺少action參數", null, 400);
        exit;
    }

    switch ($data['action']) {
        case 'vote':
            handleVote($redis, $data, $userId);
            break;
        case 'check_vote':
            checkVoteStatus($redis, $data, $userId);
            break;
        case 'batch_check_vote':
            batchCheckVoteStatus($redis, $userId);
            break;
        case 'user_vote_history':
            getUserVoteHistory($redis, $userId);
            break;
        default:
            sendJsonResponse(false, "不支持的action: {$data['action']}", null, 400);
            break;
    }
} else {
    sendJsonResponse(false, "不支持的請求方法", null, 405);
}

/**
 * 處理投票
 * @param \Predis\Client $redis Redis客戶端
 * @param array $data 請求數據
 * @param string $userId 用戶ID
 */
function handleVote($redis, $data, $userId){
    // 獲取全域變數
    global $userEmail, $userIp, $today, $maxDailyVotes, $currentTime, $currentDatetime;

    // 檢查必要參數
    if (empty($data['vote_id'])) {
        sendJsonResponse(false, "缺少vote_id參數", null, 400);
        return;
    }

    // 驗證 Cloudflare Turnstile 令牌
    if (!isset($data['cf_token']) || empty($data['cf_token'])) {
        sendJsonResponse(false, "缺少機器人驗證令牌", null, 400);
        return;
    }

    // 驗證 Turnstile 令牌
    $captchaResult = verifyCaptcha($data['cf_token'], $userIp);
    if ($captchaResult !== true) {
        sendJsonResponse(false, "機器人驗證失敗: " . $captchaResult, null, 403);
        return;
    }

    $foodId = preventXSS($data['vote_id']);

    // 使用優化後的數據結構 - 針對用戶投票記錄
    $userDailyVotesKey = "votes:daily:{$userId}:{$today}"; // 用戶當日投票的書籍集合(SET)
    $allUserVotesKey = "votes:user:{$userId}"; // 用戶所有投票記錄(SORTED SET，分數為時間戳)
    $voteLogKey = "votes:log"; // 投票日誌(LIST)

    // 書籍HASH結構的鍵
    $foodKey = "food:{$foodId}";
    $memberKey = "member:{$userId}"; // 會員資料KEY

    // 使用管道批量檢查前置條件，減少網絡往返
    $pipe = $redis->pipeline();
    $pipe->exists($foodKey);
    $pipe->exists($memberKey);
    $pipe->sismember($userDailyVotesKey, $foodId);
    $pipe->scard($userDailyVotesKey);
    $pipe->hget($foodKey, 'title');
    $pipe->hget($memberKey, 'email');
    $pipe->exists("member:discount_pin:{$userId}");
    $results = $pipe->execute();

    // 解析管道結果
    $foodExists = $results[0];
    $memberExists = $results[1];
    $alreadyVotedToday = $results[2];
    $userDailyVoteCount = $results[3];
    $foodTitle = $results[4] ?: '未知書籍';
    $memberEmail = $results[5] ?: $userEmail;
    $hasDiscountPin = $results[6];

    // 檢查書籍是否存在
    if (!$foodExists) {
        sendJsonResponse(false, "找不到指定的書籍", null, 404);
        return;
    }

    // 檢查會員資料是否存在
    if (!$memberExists) {
        sendJsonResponse(false, "找不到會員資料，請重新登入", null, 403);
        return;
    }

    // 檢查用戶當天是否已經為這本書投過票
    if ($alreadyVotedToday) {
        sendJsonResponse(false, "您今天已經為這本書投過票了，請明天再試", null, 409);
        return;
    }

    // 檢查用戶當日是否已達投票上限
    if ($userDailyVoteCount >= $maxDailyVotes) {
        sendJsonResponse(false, "您今天的投票次數已達上限，請明天再試", null, 429);
        return;
    }

    try {
        // 確保投票時間是當下的台北時間
        $voteTimestamp = time();
        $voteDatetime = getTaipeiTime('Y-m-d H:i:s');
        $discountPinData = null;

        // 開始事務，確保操作的原子性
        $redis->multi();

        // 一次性執行所有寫操作，減少事務內的I/O操作

        // 1. 增加書籍投票數並同步到排行榜
        $redis->hincrby($foodKey, 'votes', 1);

        // 創建標準格式的投票詳情
        $voteDetail = [
            'food_id' => $foodId,
            'food_title' => $foodTitle,
            'date' => $today,
            'ip' => $userIp,
            'email' => $memberEmail,
            'timestamp' => $voteTimestamp,
            'datetime' => $voteDatetime
        ];
        $voteJson = json_encode($voteDetail, JSON_UNESCAPED_UNICODE);

        // 批量更新各種投票記錄
        $redis->zadd($allUserVotesKey, $voteTimestamp, "{$today}:{$foodId}:{$voteJson}");
        $redis->sadd($userDailyVotesKey, $foodId);

        // 設置當日投票記錄的過期時間（當天結束時過期）
        $tomorrowMidnight = strtotime('tomorrow midnight');
        $redis->expireat($userDailyVotesKey, $tomorrowMidnight);

        // 記錄全局投票日誌 - 使用標準格式
        $logEntry = [
            'user_id' => $userId,
            'food_id' => $foodId,
            'food_title' => $foodTitle,
            'timestamp' => $voteTimestamp,
            'datetime' => $voteDatetime,
            'date' => $today,
            'user_ip' => $userIp,
            'user_email' => $memberEmail
        ];
        $redis->lpush($voteLogKey, json_encode($logEntry, JSON_UNESCAPED_UNICODE));
        $redis->ltrim($voteLogKey, 0, 999999); // 限制日誌長度，防止無限增長

        // 更新會員投票統計
        $redis->hincrby($memberKey, 'vote_count', 1);
        $redis->hset($memberKey, 'last_vote_time', $voteDatetime);

        // 執行事務
        $results = $redis->exec();

        // 使用syncFoodVotes函數獲取最新投票數並同步到排行榜
        $votes = syncFoodVotes($redis, $foodId);

        // 獲取用戶今日已投票的書籍清單和剩餘票數
        $todayVotedFoods = $redis->smembers($userDailyVotesKey);
        $remainingVotes = $maxDailyVotes - count($todayVotedFoods);
        $totalVoteCount = (int)$redis->hget($memberKey, 'vote_count') ?: 0;

        // 投票成功後檢查折扣碼
        if (!$hasDiscountPin && $totalVoteCount > 0) {
            $discountPinData = assignDiscountPinToMember($redis, $userId, $memberEmail);
        } elseif ($hasDiscountPin) {
            // 使用管道獲取折扣碼資料，減少網絡往返
            $discountPinData = $redis->hgetall("member:discount_pin:{$userId}");
        }

        sendJsonResponse(true, "投票成功" . ($discountPinData ? "，折扣碼資訊已更新！" : ""), [
            'vote_id' => $foodId,
            'food_title' => $foodTitle,
            'votes' => $votes,
            'daily_votes_used' => count($todayVotedFoods),
            'daily_votes_remaining' => $remainingVotes,
            'max_daily_votes' => $maxDailyVotes,
            'vote_time' => $voteDatetime,
            'today_voted_foods' => $todayVotedFoods,
            'user_info' => [
                'id' => $userId,
                'email' => $memberEmail,
                'total_vote_count' => $totalVoteCount
            ],
            'discount_pin_data' => $discountPinData // 附加折扣碼資料
        ]);
    } catch (Exception $e) {
        if (isset($redis) && $redis) {
            $redis->discard();
        }
        error_log("投票操作失敗: " . $e->getMessage());
        sendJsonResponse(false, "投票操作失敗: " . $e->getMessage(), null, 500);
    }
}

/**
 * 驗證 Cloudflare Turnstile 令牌
 * @param string $token 客戶端提供的令牌
 * @param string $remoteip 用戶IP地址
 * @return bool|string 成功返回true，失敗返回錯誤訊息
 */
function verifyCaptcha($token, $remoteip)
{
    $secret = "0x4AAAAAAA5ho0moD45CWi2cFX9SYn9fBjc"; // Cloudflare Turnstile Secret Key
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    // 設置請求數據
    $data = [
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $remoteip
    ];

    // 初始化 cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 注意：在生產環境中應設為 true

    // 執行請求
    $response = curl_exec($ch);

    // 檢查是否有錯誤
    if ($response === false) {
        error_log('Turnstile 驗證 cURL 錯誤: ' . curl_error($ch));
        curl_close($ch);
        return "驗證服務暫時不可用，請稍後再試";
    }

    curl_close($ch);

    // 解析響應
    $result = json_decode($response, true);

    // 記錄驗證結果
    error_log("Turnstile 驗證結果: " . json_encode($result, JSON_UNESCAPED_UNICODE));

    if (!$result || !isset($result['success'])) {
        return "無效的驗證響應";
    }

    // 驗證成功
    if ($result['success'] === true) {
        return true;
    }

    // 驗證失敗，返回錯誤信息
    $errorCodes = isset($result['error-codes']) ? implode(', ', $result['error-codes']) : '未知錯誤';
    return "驗證失敗: {$errorCodes}";
}

/**
 * 同步書籍投票數到foods:votes有序集合
 * @param \Predis\Client $redis Redis客戶端
 * @param string $foodId 書籍ID
 * @return int 書籍的當前投票數
 */
function syncFoodVotes($redis, $foodId)
{
    $foodKey = "food:{$foodId}";

    // 獲取書籍hash中的投票數
    $votes = (int)$redis->hget($foodKey, 'votes');

    // 同步更新到排行榜有序集合
    $redis->zadd('foods:votes', $votes, $foodId);

    return $votes;
}

/**
 * 檢查用戶是否已投票
 * @param \Predis\Client $redis Redis客戶端
 * @param array $data 請求數據
 * @param string $userId 用戶ID
 */
function checkVoteStatus($redis, $data, $userId)
{
    // 獲取用戶郵箱和IP以及其他全域變數
    global $userEmail, $userIp, $today, $maxDailyVotes;

    // 檢查必要參數
    if (empty($data['vote_id'])) {
        sendJsonResponse(false, "缺少vote_id參數", null, 400);
        return;
    }

    $foodId = preventXSS($data['vote_id']);

    // 使用優化後的數據結構
    $userDailyVotesKey = "votes:daily:{$userId}:{$today}";
    $foodKey = "food:{$foodId}";
    $memberKey = "member:{$userId}";
    $memberDiscountPinKey = "member:discount_pin:{$userId}";

    try {
        // 使用管道批量檢查，減少網絡往返
        $pipe = $redis->pipeline();
        $pipe->exists($foodKey);
        $pipe->sismember($userDailyVotesKey, $foodId);
        $pipe->hget($foodKey, 'votes');
        $pipe->hget($foodKey, 'title');
        $pipe->hgetall($memberKey);
        $pipe->smembers($userDailyVotesKey);
        $pipe->exists($memberDiscountPinKey);
        $results = $pipe->execute();

        // 解析管道結果
        $foodExists = $results[0];
        $hasVotedToday = $results[1];
        $votes = (int)$results[2] ?: 0;
        $foodTitle = $results[3];
        $memberData = $results[4];
        $todayVotedFoods = $results[5];
        $hasDiscountPin = $results[6];

        // 檢查書籍是否存在
        if (!$foodExists) {
            sendJsonResponse(false, "找不到指定的書籍", null, 404);
            return;
        }

        $totalVoteCount = isset($memberData['vote_count']) ? (int)$memberData['vote_count'] : 0;
        $userDailyVoteCount = count($todayVotedFoods);
        $remainingVotes = $maxDailyVotes - $userDailyVoteCount;

        // 獲取折扣碼資料（如果有）
        $discountPinData = null;
        if ($hasDiscountPin) {
            $discountPinData = $redis->hgetall($memberDiscountPinKey);
        }

        sendJsonResponse(true, "檢查完成", [
            'vote_id' => $foodId,
            'food_title' => $foodTitle,
            'votes' => $votes,
            'hasVotedForFoodToday' => $hasVotedToday,
            'daily_votes_used' => $userDailyVoteCount,
            'daily_votes_remaining' => $remainingVotes,
            'max_daily_votes' => $maxDailyVotes,
            'can_vote_today' => $remainingVotes > 0 && !$hasVotedToday,
            'today_voted_foods' => $todayVotedFoods,
            'user_info' => [
                'account' => $userId,
                'email' => $userEmail,
                'ip' => $userIp,
                'total_vote_count' => $totalVoteCount
            ],
            'discount_pin_data' => $discountPinData
        ]);
    } catch (Exception $e) {
        error_log("檢查投票狀態失敗: " . $e->getMessage());
        sendJsonResponse(false, "檢查投票狀態失敗: " . $e->getMessage(), null, 500);
    }
}

/**
 * 批量檢查用戶投票狀態
 * @param \Predis\Client $redis Redis客戶端
 * @param string $userId 用戶ID
 */
function batchCheckVoteStatus($redis, $userId)
{
    global $userEmail, $userIp, $today, $maxDailyVotes;

    try {
        // 使用優化後的數據結構
        $userDailyVotesKey = "votes:daily:{$userId}:{$today}";
        $memberKey = "member:{$userId}";
        $memberDiscountPinKey = "member:discount_pin:{$userId}";

        // 使用管道批量查詢，減少網絡往返
        $pipe = $redis->pipeline();
        $pipe->hgetall($memberKey);
        $pipe->smembers($userDailyVotesKey);
        $pipe->scard($userDailyVotesKey);
        $pipe->exists($memberDiscountPinKey);
        $pipe->hgetall($memberDiscountPinKey);
        $results = $pipe->execute();

        // 解析管道結果
        $memberData = $results[0];
        $todayVotedFoods = $results[1];
        $userDailyVoteCount = $results[2]; // 使用 scard 直接獲取集合大小
        $hasDiscountPin = $results[3];
        $discountPinData = $hasDiscountPin ? $results[4] : null;

        // 從會員數據中提取相關信息
        $totalVoteCount = isset($memberData['vote_count']) ? (int)$memberData['vote_count'] : 0;
        $lastVoteTime = isset($memberData['last_vote_time']) ? $memberData['last_vote_time'] : null;
        $remainingVotes = $maxDailyVotes - $userDailyVoteCount;

        // 如果用戶沒有折扣碼，但已經投過票，則分配一組折扣碼
        if (!$hasDiscountPin && ($userDailyVoteCount > 0 || $totalVoteCount > 0)) {
            $discountPinData = assignDiscountPinToMember($redis, $userId, $userEmail);
        }

        // 如果有投票的書籍，獲取書籍詳情
        $foodsDetails = [];
        if (!empty($todayVotedFoods)) {
            // 建立批量查詢管道獲取書籍詳情
            $foodPipe = $redis->pipeline();
            foreach ($todayVotedFoods as $foodId) {
                $foodPipe->hgetall("food:{$foodId}");
            }
            $foodResults = $foodPipe->execute();

            // 處理書籍詳情結果
            foreach ($todayVotedFoods as $index => $foodId) {
                $foodData = $foodResults[$index];
                if (!empty($foodData)) {
                    // 只提取需要的字段
                    $foodsDetails[$foodId] = [
                        'id' => $foodId,
                        'title' => $foodData['title'] ?? '未知書籍',
                        'votes' => (int)($foodData['votes'] ?? 0)
                    ];
                }
            }
        }

        sendJsonResponse(true, "批量檢查完成", [
            'user_id' => $userId,
            'today_voted_foods' => $todayVotedFoods,
            'foods_details' => $foodsDetails, // 添加書籍詳情
            'daily_votes_used' => $userDailyVoteCount,
            'daily_votes_remaining' => $remainingVotes,
            'max_daily_votes' => $maxDailyVotes,
            'can_vote_today' => $remainingVotes > 0,
            'user_info' => [
                'account' => $userId,
                'email' => $userEmail,
                'ip' => $userIp,
                'total_vote_count' => $totalVoteCount,
                'last_vote_time' => $lastVoteTime
            ],
            'discount_pin_data' => $discountPinData
        ]);
    } catch (Exception $e) {
        error_log("批量檢查投票狀態失敗: " . $e->getMessage());
        sendJsonResponse(false, "批量檢查投票狀態失敗: " . $e->getMessage(), null, 500);
    }
}

/**
 * 獲取用戶投票歷史
 * @param \Predis\Client $redis Redis客戶端
 * @param string $userId 用戶ID
 */
function getUserVoteHistory($redis, $userId)
{
    global $userEmail, $userIp, $today, $maxDailyVotes;

    try {
        // 使用優化後的數據結構
        $userDailyVotesKey = "votes:daily:{$userId}:{$today}";
        $allUserVotesKey = "votes:user:{$userId}";
        $memberKey = "member:{$userId}";
        $memberDiscountPinKey = "member:discount_pin:{$userId}";

        // 使用管道批量查詢，減少網絡往返
        $pipe = $redis->pipeline();
        $pipe->hgetall($memberKey);
        $pipe->smembers($userDailyVotesKey);
        $pipe->scard($userDailyVotesKey);
        $pipe->exists($memberDiscountPinKey);
        $pipe->hgetall($memberDiscountPinKey);
        $pipe->zrevrange($allUserVotesKey, 0, -1, 'WITHSCORES'); // 獲取所有投票記錄，按時間倒序
        $results = $pipe->execute();

        // 解析管道結果
        $memberData = $results[0];
        $todayVotedFoods = $results[1];
        $userDailyVoteCount = $results[2];
        $hasDiscountPin = $results[3];
        $discountPinData = $hasDiscountPin ? $results[4] : null;
        $allVotes = $results[5];

        // 從會員數據中提取相關信息
        $totalVoteCount = isset($memberData['vote_count']) ? (int)$memberData['vote_count'] : 0;
        $firstLoginTime = $memberData['first_login_time'] ?? null;
        $lastLoginTime = $memberData['last_login_time'] ?? null;
        $loginCount = isset($memberData['login_count']) ? (int)$memberData['login_count'] : 0;

        // 處理投票詳情 - 使用更高效的算法
        $voteDetails = [];
        $votesByDate = [];
        $foodIds = []; // 收集所有書籍ID用於批量查詢

        // 第一步：解析所有投票記錄，同時收集書籍ID
        foreach ($allVotes as $voteKey => $timestamp) {
            // 解析投票記錄 (格式是 date:foodId:voteDetail)
            $parts = explode(':', $voteKey, 3);
            if (count($parts) >= 3) {
                $date = $parts[0];
                $foodId = $parts[1];
                $foodIds[] = $foodId; // 收集書籍ID
                $voteDetailJson = $parts[2];

                $detail = json_decode($voteDetailJson, true);
                if ($detail) {
                    $detail['date'] = $date;
                    $detail['food_id'] = $foodId;
                    $detail['timestamp'] = isset($detail['timestamp']) ? $detail['timestamp'] : $timestamp;
                    $detail['datetime'] = isset($detail['datetime']) ?
                        $detail['datetime'] : convertToTaipeiTime($timestamp, 'Y-m-d H:i:s');
                    $voteDetails[] = $detail;

                    // 按日期分組
                    if (!isset($votesByDate[$date])) {
                        $votesByDate[$date] = [];
                    }
                    $votesByDate[$date][] = $detail;
                }
            }
        }

        // 如果有書籍ID，批量獲取書籍詳情
        $foodDetails = [];
        $uniqueFoodIds = array_unique($foodIds);
        if (!empty($uniqueFoodIds)) {
            // 建立批量查詢管道獲取書籍詳情
            $foodPipe = $redis->pipeline();
            foreach ($uniqueFoodIds as $foodId) {
                $foodPipe->hgetall("food:{$foodId}");
            }
            $foodResults = $foodPipe->execute();

            // 處理書籍詳情結果
            foreach ($uniqueFoodIds as $index => $foodId) {
                $foodData = $foodResults[$index];
                if (!empty($foodData)) {
                    // 只保存需要的書籍信息
                    $foodDetails[$foodId] = [
                        'id' => $foodId,
                        'title' => $foodData['title'] ?? '未知書籍',
                        'votes' => (int)($foodData['votes'] ?? 0),
                        'author' => $foodData['author'] ?? '',
                        'publisher' => $foodData['publisher'] ?? ''
                    ];
                }
            }
        }

        // 第二步：將書籍詳情關聯到投票記錄
        foreach ($voteDetails as &$detail) {
            $foodId = $detail['food_id'];
            if (isset($foodDetails[$foodId])) {
                $detail['food_title'] = $foodDetails[$foodId]['title'];
                $detail['food_votes'] = $foodDetails[$foodId]['votes'];
                $detail['food_publisher'] = $foodDetails[$foodId]['publisher'];
            }
        }
        unset($detail); // 釋放引用

        // 按日期進行遞減排序
        krsort($votesByDate);

        // 獲取當日投票的書籍詳情
        $todayVotedFoodsDetails = [];
        if (!empty($todayVotedFoods)) {
            foreach ($todayVotedFoods as $foodId) {
                if (isset($foodDetails[$foodId])) {
                    $todayVotedFoodsDetails[$foodId] = $foodDetails[$foodId];
                }
            }
        }

        sendJsonResponse(true, "獲取用戶投票歷史成功", [
            'user_id' => $userId,
            'user_email' => $userEmail,
            'user_ip' => $userIp,
            'total_vote_count' => $totalVoteCount,
            'daily_votes_used' => $userDailyVoteCount,
            'daily_votes_remaining' => $maxDailyVotes - $userDailyVoteCount,
            'max_daily_votes' => $maxDailyVotes,
            'today_voted_foods' => $todayVotedFoods,
            'today_voted_foods_details' => $todayVotedFoodsDetails, // 添加書籍詳情
            'vote_history' => $voteDetails,
            'votes_by_date' => $votesByDate,
            'member_info' => [
                'id' => $userId,
                'email' => $userEmail,
                'ip' => $userIp,
                'first_login_time' => $firstLoginTime,
                'last_login_time' => $lastLoginTime,
                'login_count' => $loginCount,
                'total_vote_count' => $totalVoteCount
            ],
            'discount_pin_data' => $discountPinData
        ]);
    } catch (Exception $e) {
        error_log("獲取用戶投票歷史失敗: " . $e->getMessage());
        sendJsonResponse(false, "獲取用戶投票歷史失敗: " . $e->getMessage(), null, 500);
    }
}

/**
 * 為會員分配一組折扣碼和PIN碼
 * @param \Predis\Client $redis Redis客戶端
 * @param string $memberId 會員ID
 * @param string $email 會員電子郵件
 * @return array|null 折扣碼資料，若無可用折扣碼則返回null
 */
function assignDiscountPinToMember($redis, $memberId, $email)
{
    try {
        // 先檢查會員是否已有折扣碼
        $memberDiscountPinKey = "member:discount_pin:{$memberId}";
        if ($redis->exists($memberDiscountPinKey)) {
            // 如果已有折扣碼，直接返回已有的資料
            $existingPin = $redis->hgetall($memberDiscountPinKey);
            error_log("會員 {$memberId} 已有折扣碼，跳過分配: {$existingPin['discount_code']}");
            return $existingPin;
        }

        // 使用原子操作從可用集合中移除並獲取一個折扣碼ID
        // SPOP 保證不會有競爭條件 (race condition)
        $pinId = $redis->spop('pins:available');

        // 如果沒有可用的折扣碼
        if (!$pinId) {
            error_log("沒有可用的折扣碼供分配給會員 {$memberId}");
            return null;
        }

        $pinKey = "pin:{$pinId}";

        // 檢查PIN碼是否存在
        if (!$redis->exists($pinKey)) {
            error_log("找不到指定的PIN碼: {$pinId}");
            // 如果發現無效的PIN ID，將它從其他集合中也移除
            $redis->srem('pins:used', $pinId);
            return null;
        }

        // 獲取PIN碼資料
        $pinData = $redis->hgetall($pinKey);

        // 再次檢查此PIN碼是否已被標記為使用過
        if (isset($pinData['is_used']) && $pinData['is_used'] === '1') {
            error_log("PIN碼 {$pinId} 已被標記為使用過，但仍在可用集合中，修正數據不一致問題");
            $redis->sadd('pins:used', $pinId); // 確保它在已用集合中
            return null;
        }

        // 開始事務處理
        $redis->multi();

        // 標記折扣碼為已使用
        $redis->hset($pinKey, 'is_used', '1');
        $redis->hset($pinKey, 'used_at', getTaipeiTime('Y-m-d H:i:s'));
        $redis->hset($pinKey, 'member_id', $memberId);
        $redis->hset($pinKey, 'member_email', $email);

        // 加入已使用集合 (注意: 我們已經使用SPOP從可用集合中移除)
        $redis->sadd('pins:used', $pinId);

        // 創建會員與折扣碼的關聯
        $redis->hmset($memberDiscountPinKey, [
            'pin_id' => $pinId,
            'discount_code' => $pinData['discount_code'],
            'pin_code' => $pinData['pin_code'],
            'issued_at' => getTaipeiTime('Y-m-d H:i:s')
        ]);

        // 執行事務
        $redis->exec();

        // 記錄分配動作
        error_log("已分配折扣碼給會員 {$memberId}: PIN ID {$pinId}, 折扣碼: {$pinData['discount_code']}");

        // 返回折扣碼資料
        return [
            'pin_id' => $pinId,
            'discount_code' => $pinData['discount_code'],
            'pin_code' => $pinData['pin_code'],
            'issued_at' => getTaipeiTime('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        error_log("分配折扣碼給會員 {$memberId} 失敗: " . $e->getMessage());
        return null;
    }
}

/**
 * 發送JSON響應
 * @param bool $success 是否成功
 * @param string $message 消息
 * @param mixed $data 數據
 * @param int $statusCode HTTP狀態碼
 */
function sendJsonResponse($success, $message, $data = null, $statusCode = 200)
{
    http_response_code($statusCode);

    $response = [
        'success' => $success,
        'message' => $message,
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 加強參數驗證
function validateVoteInput($data) {
    $errors = [];
    
    // 驗證 vote_id
    if (empty($data['vote_id'])) {
        $errors[] = "vote_id 不能為空";
    } elseif (!is_numeric($data['vote_id']) || $data['vote_id'] <= 0) {
        $errors[] = "vote_id 必須為正整數";
    } elseif ($data['vote_id'] > 125) { // 根據您的食物數量限制
        $errors[] = "vote_id 超出有效範圍";
    }
    
    // 驗證 action
    $validActions = ['vote', 'check_vote', 'batch_check_vote', 'user_vote_history'];
    if (empty($data['action']) || !in_array($data['action'], $validActions)) {
        $errors[] = "action 參數無效";
    }
    
    // 驗證 cf_token (Cloudflare Turnstile)
    if ($data['action'] === 'vote') {
        if (empty($data['cf_token'])) {
            $errors[] = "缺少機器人驗證令牌";
        } elseif (!is_string($data['cf_token']) || strlen($data['cf_token']) < 10) {
            $errors[] = "機器人驗證令牌格式無效";
        }
    }
    
    return $errors;
}
//強化 XSS 防護
function validateAndSanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            // 驗證鍵名
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                throw new InvalidArgumentException("無效的參數名稱: $key");
            }
            
            $data[$key] = validateAndSanitizeInput($value);
        }
    } elseif (is_string($data)) {
        // 移除潛在的惡意內容
        $data = strip_tags($data);
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // 檢查長度限制
        if (strlen($data) > 1000) {
            throw new InvalidArgumentException("輸入內容過長");
        }
    }
    
    return $data;
}