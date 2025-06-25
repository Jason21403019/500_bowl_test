<?php
// filepath: c:\Users\1\Documents\bd_500bowls_vote2025\src\API\backend.php

// 引入CORS設置和Redis連接
require_once 'cors.php';
require_once 'redis.php';
require_once 'timezone.php'; // 引入台北時間設定

// 設置內容類型為JSON
header('Content-Type: application/json; charset=utf-8');

// 定義全域變數
$today = getTaipeiTime('Y-m-d');

// 全域食物快取
$globalFoodCache = [];

// 獲取食物標題的統一函數
function getFoodTitle($redis, $foodId, &$foodCache = null)
{
    global $globalFoodCache;

    // 使用傳入的快取或全域快取
    $cache = &$foodCache ?: $globalFoodCache;

    if (!isset($cache[$foodId])) {
        $cache[$foodId] = $redis->hget("food:{$foodId}", 'title') ?: '未知食物';
    }

    return $cache[$foodId];
}

// 獲取用戶投票記錄
function getVoteLogs($redis)
{
    try {
        // 獲取請求參數並進行驗證
        $userId = isset($_GET['user_id']) ? htmlspecialchars(trim($_GET['user_id']), ENT_QUOTES, 'UTF-8') : null;
        $page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1);
        $limit = min(100, max(1, filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?? 20));
        $startDate = isset($_GET['start_date']) ? htmlspecialchars(trim($_GET['start_date']), ENT_QUOTES, 'UTF-8') : null;
        $endDate = isset($_GET['end_date']) ? htmlspecialchars(trim($_GET['end_date']), ENT_QUOTES, 'UTF-8') : null;
        $format = isset($_GET['format']) ? htmlspecialchars(trim($_GET['format']), ENT_QUOTES, 'UTF-8') : 'grouped';
        $searchKeyword = isset($_GET['search']) ? htmlspecialchars(trim($_GET['search']), ENT_QUOTES, 'UTF-8') : null;
        $foodId = isset($_GET['food_id']) ? htmlspecialchars(trim($_GET['food_id']), ENT_QUOTES, 'UTF-8') : null;
        $sortBy = isset($_GET['sort_by']) ? htmlspecialchars(trim($_GET['sort_by']), ENT_QUOTES, 'UTF-8') : 'date';
        $sortOrder = isset($_GET['sort_order']) ? htmlspecialchars(trim($_GET['sort_order']), ENT_QUOTES, 'UTF-8') : 'desc';

        // 驗證排序參數
        $validSortByValues = ['date', 'user_id', 'food_title'];
        $validSortOrderValues = ['asc', 'desc'];

        if (!in_array($sortBy, $validSortByValues)) {
            $sortBy = 'date';
        }
        if (!in_array($sortOrder, $validSortOrderValues)) {
            $sortOrder = 'desc';
        }

        // 驗證日期格式
        if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            throw new Exception('無效的開始日期格式');
        }
        if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new Exception('無效的結束日期格式');
        }

        // 轉換日期格式
        $startDate = $startDate ? convertToTaipeiTime($startDate) : null;
        $endDate = $endDate ? convertToTaipeiTime($endDate) : null;

        // 如果指定了用戶ID，直接從該用戶的專屬日誌獲取記錄
        if ($userId) {
            return getUserVoteLogs($redis, $userId, $page, $limit, $startDate, $endDate, $searchKeyword, $foodId);
        }

        // 計算分頁偏移
        $offset = ($page - 1) * $limit;

        // 獲取投票日誌
        $logs = [];
        $dataSources = [];
        $processedVotes = [];

        // 1. 從用戶投票有序集合中獲取數據
        $userVotesKeys = $redis->keys("votes:user:*");
        if (!empty($userVotesKeys)) {
            $dataSources[] = 'votes:user:*';
            $logs = array_merge($logs, getUserVotesFromKeys($redis, $userVotesKeys, $processedVotes));
        }

        // 2. 從每日投票記錄補充數據
        $dailyVoteKeys = $redis->keys("votes:daily:*:*");
        if (!empty($dailyVoteKeys)) {
            $dataSources[] = 'votes:daily:*';
            $logs = array_merge($logs, getDailyVotesFromKeys($redis, $dailyVoteKeys, $processedVotes));
        }

        // 過濾並處理日誌
        $filteredLogs = filterAndProcessLogs($logs, $userId, $foodId, $startDate, $endDate, $searchKeyword);

        // 排序處理後的日誌
        $sortedLogs = sortLogs($filteredLogs, $sortBy, $sortOrder);

        // 應用分頁
        $paginatedLogs = array_slice($sortedLogs, $offset, $limit);

        // 按日期分組
        $votesByDate = groupVotesByDate($paginatedLogs);

        // 按用戶分組
        $groupedByUser = groupVotesByUser($paginatedLogs);

        // 準備分頁信息
        $pagination = [
            'total' => count($filteredLogs),
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil(count($filteredLogs) / $limit)
        ];

        // 發送響應
        sendJsonResponse(true, $format === 'grouped' ? "獲取分組投票記錄成功" : "獲取投票記錄成功", [
            $format === 'grouped' ? 'grouped_by_user' : 'votes' => $format === 'grouped' ? array_values($groupedByUser) : $paginatedLogs,
            'votes_by_date' => $votesByDate,
            'pagination' => $pagination,
            'filters' => [
                'user_id' => $userId,
                'food_id' => $foodId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $searchKeyword,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ],
            'data_sources' => $dataSources
        ]);
    } catch (Exception $e) {
        error_log("獲取投票記錄失敗: " . $e->getMessage());
        sendJsonResponse(false, "獲取投票記錄失敗: " . $e->getMessage(), null, 500);
    }
}

// 從用戶投票鍵中獲取數據
function getUserVotesFromKeys($redis, $keys, &$processedVotes)
{
    $logs = [];
    static $memberCache = [];
    static $foodCache = [];

    foreach ($keys as $key) {
        preg_match('/votes:user:(.+)/', $key, $matches);
        $uid = $matches[1] ?? 'unknown';

        // 獲取用戶信息（使用快取）
        if (!isset($memberCache[$uid])) {
            $memberKey = "member:{$uid}";
            $memberCache[$uid] = $redis->hgetall($memberKey) ?: [];
        }
        $memberData = $memberCache[$uid];
        $entries = $redis->zrange($key, 0, -1, 'WITHSCORES');
        foreach ($entries as $voteKey => $timestamp) {
            $uniqueKey = "{$uid}:{$voteKey}";
            if (isset($processedVotes[$uniqueKey])) continue;
            $processedVotes[$uniqueKey] = true;

            $voteData = parseVoteKey($voteKey, $timestamp, $uid, $memberData, $redis, $foodCache);
            if ($voteData) {
                $logs[] = $voteData;
            }
        }
    }
    return $logs;
}

// 從每日投票鍵中獲取數據
function getDailyVotesFromKeys($redis, $keys, &$processedVotes)
{
    $logs = [];
    $processedDailyVotes = [];
    static $memberCache = [];
    static $foodCache = [];

    foreach ($keys as $key) {
        if (preg_match('/votes:daily:(.+?):(\d{4}-\d{2}-\d{2})$/', $key, $matches)) {
            $uid = $matches[1];
            $date = $matches[2];

            $uniqueKey = "{$uid}:{$date}";
            if (isset($processedDailyVotes[$uniqueKey])) continue;
            $processedDailyVotes[$uniqueKey] = true;

            // 獲取用戶信息（使用快取）
            if (!isset($memberCache[$uid])) {
                $memberKey = "member:{$uid}";
                $memberCache[$uid] = $redis->hgetall($memberKey) ?: [];
            }
            $memberData = $memberCache[$uid];

            $votedFoods = $redis->smembers($key);
            foreach ($votedFoods as $foodId) {
                $uniqueVoteKey = "{$uid}:{$foodId}:{$date}";
                if (isset($processedVotes[$uniqueVoteKey])) continue;
                $processedVotes[$uniqueVoteKey] = true;

                // 使用食物快取
                if (!isset($foodCache[$foodId])) {
                    $foodCache[$foodId] = $redis->hget("food:{$foodId}", 'title') ?: '未知食物';
                }

                $voteTimestamp = strtotime($date);
                $logs[] = [
                    'user_id' => $uid,
                    'food_id' => $foodId,
                    'food_title' => $foodCache[$foodId],
                    'timestamp' => $voteTimestamp,
                    'datetime' => convertToTaipeiTime($voteTimestamp, 'Y-m-d H:i:s'),
                    'date' => $date,
                    'user_ip' => $memberData['last_ip'] ?? '',
                    'user_email' => $memberData['email'] ?? '',
                    'data_source' => 'votes:daily (補充)'
                ];
            }
        }
    }
    return $logs;
}

// 解析投票鍵
function parseVoteKey($voteKey, $timestamp, $uid, $memberData, $redis, &$foodCache = null)
{
    if (strpos($voteKey, ':') === false) return null;

    $parts = explode(':', $voteKey, 3);
    if (count($parts) < 3) return null;

    [$date, $foodId, $voteDetailJson] = $parts;

    // 處理JSON格式問題
    if (substr($voteDetailJson, 0, 1) !== '{') {
        $voteDetailJson = '{' . $voteDetailJson;
    }
    if (substr($voteDetailJson, -1) !== '}') {
        $voteDetailJson .= '}';
    }

    $detail = json_decode($voteDetailJson, true);
    if (!$detail) return null;

    // 獲取食物標題（使用外部快取或內部靜態快取）
    if ($foodCache !== null) {
        if (!isset($foodCache[$foodId])) {
            $foodCache[$foodId] = $redis->hget("food:{$foodId}", 'title') ?: '未知食物';
        }
        $foodTitle = $foodCache[$foodId];
    } else {
        static $internalFoodCache = [];
        if (!isset($internalFoodCache[$foodId])) {
            $internalFoodCache[$foodId] = $redis->hget("food:{$foodId}", 'title') ?: '未知食物';
        }
        $foodTitle = $internalFoodCache[$foodId];
    }
    return [
        'user_id' => $uid,
        'food_id' => $foodId,
        'food_title' => $foodTitle,
        'timestamp' => isset($detail['timestamp']) ? (int)$detail['timestamp'] : $timestamp,
        'datetime' => convertToTaipeiTime($timestamp, 'Y-m-d H:i:s'),
        'date' => $date,
        'user_ip' => $detail['ip'] ?? $memberData['last_ip'] ?? '',
        'user_email' => $detail['email'] ?? $memberData['email'] ?? '',
        'data_source' => 'votes:user'
    ];
}

// 過濾和處理日誌
function filterAndProcessLogs($logs, $userId, $foodId, $startDate, $endDate, $searchKeyword)
{
    return array_filter($logs, function ($log) use ($userId, $foodId, $startDate, $endDate, $searchKeyword) {
        if ($userId && $log['user_id'] !== $userId) return false;
        if ($foodId && $log['food_id'] !== $foodId) return false;
        if ($startDate && strtotime($log['date']) < strtotime($startDate)) return false;
        if ($endDate && strtotime($log['date']) > strtotime($endDate)) return false;

        if ($searchKeyword) {
            $keyword = strtolower($searchKeyword);
            return stripos($log['user_id'], $keyword) !== false ||
                stripos($log['user_email'] ?? '', $keyword) !== false ||
                stripos($log['food_title'], $keyword) !== false ||
                stripos($log['user_ip'] ?? '', $keyword) !== false;
        }

        return true;
    });
}

// 排序日誌
function sortLogs($logs, $sortBy, $sortOrder)
{
    $sortFunction = function ($a, $b) use ($sortBy, $sortOrder) {
        $multiplier = $sortOrder === 'asc' ? 1 : -1;

        switch ($sortBy) {
            case 'date':
                return $multiplier * (strtotime($a['date']) - strtotime($b['date']));
            case 'user_id':
                return $multiplier * strcmp($a['user_id'], $b['user_id']);
            case 'food_title':
                return $multiplier * strcmp($a['food_title'], $b['food_title']);
            default:
                return $multiplier * (strtotime($a['date']) - strtotime($b['date']));
        }
    };

    $sortedLogs = $logs;
    usort($sortedLogs, $sortFunction);
    return $sortedLogs;
}

// 按日期分組
function groupVotesByDate($logs)
{
    $grouped = [];
    foreach ($logs as $log) {
        $date = $log['date'];
        if (!isset($grouped[$date])) {
            $grouped[$date] = [];
        }
        $grouped[$date][] = $log;
    }
    krsort($grouped);
    return $grouped;
}

// 按用戶分組
function groupVotesByUser($logs)
{
    $grouped = [];
    foreach ($logs as $log) {
        $uid = $log['user_id'];
        if (!isset($grouped[$uid])) {
            $grouped[$uid] = [
                'user_id' => $uid,
                'user_email' => $log['user_email'] ?? '',
                'user_ip' => $log['user_ip'] ?? '',
                'votes' => []
            ];
        }

        $grouped[$uid]['votes'][] = [
            'food_id' => $log['food_id'],
            'food_title' => $log['food_title'],
            'timestamp' => $log['timestamp'],
            'datetime' => $log['datetime'],
            'date' => $log['date'],
            'data_source' => $log['data_source']
        ];
    }

    foreach ($grouped as &$user) {
        $user['vote_count'] = count($user['votes']);
    }

    return $grouped;
}

// 獲取用戶投票日誌 - 優化版本，先獲取會員資料再關聯投票
function getUserVoteLogs($redis, $userId, $page, $limit, $startDate, $endDate, $searchKeyword, $foodId)
{
    // 驗證輸入參數
    $userId = htmlspecialchars(trim($userId), ENT_QUOTES, 'UTF-8');
    $searchKeyword = $searchKeyword ? htmlspecialchars(trim($searchKeyword), ENT_QUOTES, 'UTF-8') : null;
    $foodId = $foodId ? htmlspecialchars(trim($foodId), ENT_QUOTES, 'UTF-8') : null;

    // 驗證日期格式
    if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        throw new Exception('無效的開始日期格式');
    }
    if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        throw new Exception('無效的結束日期格式');
    }

    // 計算分頁偏移
    $offset = ($page - 1) * $limit;

    // 1. 獲取用戶信息
    $memberKey = "member:{$userId}";
    $memberData = $redis->hgetall($memberKey) ?: [];

    // 2. 獲取折扣卡信息（只在需要時查詢）
    $memberDiscountPinKey = "member:discount_pin:{$userId}";
    $discountPinData = $redis->hgetall($memberDiscountPinKey) ?: null;

    // 3. 獲取用戶投票集合
    $userVotesKey = "votes:user:{$userId}";
    $userVotesCount = $redis->zcard($userVotesKey);
    $userVotesExists = $userVotesCount > 0;

    // 4. 獲取投票詳情
    $logs = [];
    $dataSourceInfo = [];
    $foodCache = []; // 添加食物快取

    if ($userVotesExists) {
        $dataSourceInfo['votes_user'] = [
            'exists' => true,
            'count' => $userVotesCount
        ];

        // 獲取所有投票記錄 - 使用標準的 zrange 命令獲取所有記錄
        $voteRecords = $redis->zrange($userVotesKey, 0, -1, 'WITHSCORES');

        // 處理每條投票記錄
        foreach ($voteRecords as $voteKey => $timestamp) {
            if (strpos($voteKey, ':') !== false) {
                $parts = explode(':', $voteKey, 3);
                if (count($parts) >= 3) {
                    $date = $parts[0];
                    $foodIdFromKey = $parts[1];
                    $voteDetailJson = $parts[2];

                    // 處理JSON格式問題
                    if (substr($voteDetailJson, 0, 1) !== '{') {
                        $voteDetailJson = '{' . $voteDetailJson;
                    }
                    if (substr($voteDetailJson, -1) !== '}') {
                        $voteDetailJson .= '}';
                    }

                    // 嘗試解析 JSON
                    $detail = json_decode($voteDetailJson, true);

                    if ($detail) {
                        // 從快取中獲取食物標題
                        $foodTitle = $detail['food_title'] ?? null;
                        if (!$foodTitle && !empty($foodIdFromKey)) {
                            if (!isset($foodCache[$foodIdFromKey])) {
                                $foodCache[$foodIdFromKey] = $redis->hget("food:{$foodIdFromKey}", 'title') ?: '未知食物';
                            }
                            $foodTitle = $foodCache[$foodIdFromKey];
                        }

                        // 使用原始記錄中的時間戳，如果有的話
                        $actualTimestamp = isset($detail['timestamp']) ? (int)$detail['timestamp'] : $timestamp;

                        // 構建日誌條目
                        $logEntry = [
                            'user_id' => $userId,
                            'food_id' => $foodIdFromKey,
                            'food_title' => $foodTitle,
                            'timestamp' => $actualTimestamp,
                            'datetime' => convertToTaipeiTime($actualTimestamp, 'Y-m-d H:i:s'),
                            'date' => $date,
                            'user_ip' => $detail['ip'] ?? ($memberData['last_ip'] ?? ''),
                            'user_email' => $detail['email'] ?? ($memberData['email'] ?? ''),
                            'data_source' => 'votes:user'
                        ];
                        $logs[] = $logEntry;
                    }
                }
            }
        }
    } else {
        $dataSourceInfo['votes_user'] = ['exists' => false];
    }

    // 按時間戳降序排序投票記錄
    usort($logs, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    // 直接使用所有日誌，不做過濾
    $filteredLogs = $logs;
    // $votesByDate = [];

    // 按日期分組
    foreach ($filteredLogs as $log) {
        if (isset($log['date'])) {
            $date = $log['date'];
            if (!isset($votesByDate[$date])) {
                $votesByDate[$date] = [];
            }
            $votesByDate[$date][] = $log;
        }
    }

    // 計算每日投票數量
    $voteCountByDate = [];
    foreach ($votesByDate as $date => $votes) {
        $voteCountByDate[$date] = count($votes);
    }

    // 日期遞減排序
    krsort($votesByDate);

    // 準備分頁信息
    $pagination = [
        'total' => count($filteredLogs),
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil(count($filteredLogs) / $limit)
    ];

    // 最終輸出結果 - 返回所有投票記錄
    sendJsonResponse(true, "獲取用戶投票記錄成功", [
        'user_id' => $userId,
        'user_email' => $memberData['email'] ?? '',
        'user_ip' => $memberData['last_ip'] ?? '',
        'total_vote_count' => count($filteredLogs),
        'votes' => $filteredLogs,  // 直接返回所有記錄
        'votes_by_date' => $votesByDate,
        'vote_count_by_date' => $voteCountByDate,
        'pagination' => $pagination,
        'member_info' => $memberData,
        'discount_pin_data' => $discountPinData,
        'data_sources' => $dataSourceInfo
    ]);
}

// 獲取所有用戶列表 - 重構版本，使用 Pipeline 優化
function getAllUsers($redis)
{
    try {
        // 使用多種方式嘗試獲取用戶數據
        $users = [];
        $userIds = [];
        $dataSources = []; // 記錄數據來源

        // 從會員索引集合獲取所有會員ID (優先數據源)
        $memberIds = $redis->smembers('members:index');

        // 記錄進度
        $progress = ['total_members' => count($memberIds), 'processed' => 0];

        if (!empty($memberIds)) {
            $dataSources[] = 'members:index';

            // 使用 pipeline 批量獲取會員數據
            $pipeline = $redis->pipeline();
            $memberKeys = [];

            // 為每個會員ID添加到pipeline
            foreach ($memberIds as $memberId) {
                $memberKey = "member:{$memberId}";
                $memberKeys[$memberId] = $memberKey;
                $pipeline->exists($memberKey);
                $pipeline->hgetall($memberKey);
            }

            // 執行pipeline
            $pipelineResults = $pipeline->execute();

            // 處理pipeline結果
            $userMap = [];
            $resultIndex = 0;

            foreach ($memberIds as $memberId) {
                $progress['processed']++;

                if (!in_array($memberId, $userIds)) {
                    $userIds[] = $memberId;

                    // 獲取exists和hgetall的結果
                    $exists = $pipelineResults[$resultIndex++];
                    $memberData = $pipelineResults[$resultIndex++];

                    if ($exists && !empty($memberData)) {
                        // 構建用戶基本信息
                        $userMap[$memberId] = [
                            'user_id' => $memberId,
                            'vote_count' => isset($memberData['vote_count']) ? (int)$memberData['vote_count'] : 0,
                            'user_email' => $memberData['email'] ?? '',
                            'user_ip' => $memberData['last_ip'] ?? '',
                            'first_login_time' => $memberData['first_login_time'] ?? '',
                            'last_login_time' => $memberData['last_login_time'] ?? '',
                            'login_count' => isset($memberData['login_count']) ? (int)$memberData['login_count'] : 0,
                            'data_source' => 'members:index',
                            'has_votes_collection' => false,
                            'votes_details' => []
                        ];
                    }
                }
            }

            // 使用pipeline為每個用戶獲取投票詳情
            $pipeline = $redis->pipeline();
            $userVoteKeys = [];
            $today = getTaipeiTime('Y-m-d');

            foreach ($userMap as $userId => $userData) {
                $userVotesKey = "votes:user:{$userId}";
                $todayVotesKey = "votes:daily:{$userId}:{$today}";

                $userVoteKeys[$userId] = [
                    'votes_key' => $userVotesKey,
                    'today_key' => $todayVotesKey
                ];

                // 添加到pipeline
                $pipeline->zcard($userVotesKey);  // 投票總數
                $pipeline->zrevrange($userVotesKey, 0, 2, 'WITHSCORES'); // 最近投票
                $pipeline->scard($todayVotesKey); // 今日投票數
                $pipeline->smembers($todayVotesKey); // 今日投票食物
            }

            // 執行pipeline
            $voteResults = $pipeline->execute();

            // 處理投票詳情結果
            $progress['vote_collections_checked'] = 0;
            $foodCache = [];
            $resultIndex = 0;

            foreach ($userMap as $userId => &$userData) {
                $progress['vote_collections_checked']++;

                // 獲取pipeline結果
                $voteCount = $voteResults[$resultIndex++];
                $recentVotes = $voteResults[$resultIndex++];
                $todayVoteCount = $voteResults[$resultIndex++];
                $todayVotedFoods = $voteResults[$resultIndex++];

                // 處理投票集合數據
                if ($voteCount > 0) {
                    $userData['has_votes_collection'] = true;

                    // 只有當投票計數不一致時才更新
                    if ($voteCount != $userData['vote_count']) {
                        $userData['vote_count'] = $voteCount;
                        $userData['data_source'] .= ', votes:user:*';
                    }

                    // 處理最近的投票詳情
                    if (!empty($recentVotes)) {
                        foreach ($recentVotes as $voteKey => $timestamp) {
                            // 解析投票記錄 (格式是 date:foodId:voteDetailJson)
                            $parts = explode(':', $voteKey, 3);
                            if (count($parts) >= 3) {
                                $date = $parts[0];
                                $foodId = $parts[1];
                                $voteDetailJson = $parts[2];

                                // 嘗試從投票詳情獲取實際時間戳
                                $actualTimestamp = $timestamp;
                                $detailData = json_decode($voteDetailJson, true);
                                if ($detailData && isset($detailData['timestamp'])) {
                                    $actualTimestamp = (int)$detailData['timestamp'];
                                }

                                // 標記需要獲取食物標題
                                if (!isset($foodCache[$foodId])) {
                                    $foodCache[$foodId] = null; // 標記待獲取
                                }

                                $userData['votes_details'][] = [
                                    'date' => $date,
                                    'food_id' => $foodId,
                                    'food_title' => null, // 稍後填充
                                    'timestamp' => $actualTimestamp,
                                    'datetime' => convertToTaipeiTime($actualTimestamp, 'Y-m-d H:i:s')
                                ];
                            }
                        }
                    }
                }

                // 處理今日投票數據
                if ($todayVoteCount > 0) {
                    $userData['has_voted_today'] = true;
                    $userData['today_vote_count'] = $todayVoteCount;
                    $userData['today_voted_foods'] = $todayVotedFoods;
                    $userData['data_source'] .= ', votes:daily:*';
                } else {
                    $userData['has_voted_today'] = false;
                    $userData['today_vote_count'] = 0;
                    $userData['today_voted_foods'] = [];
                }
            }

            // 使用pipeline批量獲取食物標題
            if (!empty($foodCache)) {
                $pipeline = $redis->pipeline();
                $foodIds = array_keys($foodCache);

                foreach ($foodIds as $foodId) {
                    $pipeline->hget("food:{$foodId}", 'title');
                }

                $foodTitleResults = $pipeline->execute();

                // 填充食物快取
                foreach ($foodIds as $index => $foodId) {
                    $foodCache[$foodId] = $foodTitleResults[$index] ?: '未知食物';
                }

                // 更新用戶數據中的食物標題
                foreach ($userMap as &$userData) {
                    foreach ($userData['votes_details'] as &$voteDetail) {
                        if (isset($foodCache[$voteDetail['food_id']])) {
                            $voteDetail['food_title'] = $foodCache[$voteDetail['food_id']];
                        }
                    }
                }
            }

            // 轉換用戶映射為陣列
            $users = array_values($userMap);
        }

        // 尋找不在會員索引中的用戶 (從投票記錄中補充)
        $otherUsersSources = [];

        // 1. 從 votes:user:* 有序集合獲取用戶 - 使用 pipeline 優化
        $pattern = "votes:user:*";
        $voteUserKeys = $redis->keys($pattern);
        $otherUsersSources['votes_user_keys'] = count($voteUserKeys);

        if (!empty($voteUserKeys)) {
            $dataSources[] = 'votes:user:* (補充)';

            // 使用 pipeline 批量處理投票用戶數據
            $pipeline = $redis->pipeline();
            $voteUserData = [];

            // 第一輪：獲取用戶基本信息和投票數據
            foreach ($voteUserKeys as $key) {
                preg_match('/votes:user:(.+)/', $key, $matches);
                if (isset($matches[1])) {
                    $userId = $matches[1];
                    if (!in_array($userId, $userIds)) {
                        $memberKey = "member:{$userId}";
                        $voteUserData[$userId] = [
                            'key' => $key,
                            'member_key' => $memberKey
                        ];

                        // 添加到 pipeline
                        $pipeline->hgetall($memberKey); // 會員資料
                        $pipeline->zcard($key); // 投票計數
                        $pipeline->zrevrange($key, 0, 2, 'WITHSCORES'); // 最近投票記錄
                    }
                }
            }

            // 執行第一輪 pipeline
            $userDataResults = $pipeline->execute();

            // 處理結果並準備食物標題查詢
            $resultIndex = 0;
            $foodIdsToFetch = [];
            $userVoteDetails = [];

            foreach ($voteUserData as $userId => $keyData) {
                if (!in_array($userId, $userIds)) {
                    $userIds[] = $userId;

                    // 獲取 pipeline 結果
                    $memberData = $userDataResults[$resultIndex++] ?: [];
                    $voteCount = $userDataResults[$resultIndex++];
                    $voteRecords = $userDataResults[$resultIndex++];

                    // 從投票記錄中提取用戶信息
                    $userEmail = '';
                    $userIp = '';
                    $voteDetails = [];

                    foreach ($voteRecords as $voteKey => $timestamp) {
                        $parts = explode(':', $voteKey, 3);
                        if (count($parts) >= 3) {
                            $date = $parts[0];
                            $foodId = $parts[1];
                            $voteDetailJson = $parts[2];

                            // 處理可能不完整的JSON
                            if (substr($voteDetailJson, 0, 1) !== '{') {
                                $voteDetailJson = '{' . $voteDetailJson;
                            }
                            if (substr($voteDetailJson, -1) !== '}') {
                                $voteDetailJson .= '}';
                            }

                            $voteDetail = json_decode($voteDetailJson, true);
                            if ($voteDetail) {
                                if (empty($userEmail) && isset($voteDetail['email'])) {
                                    $userEmail = $voteDetail['email'];
                                }
                                if (empty($userIp) && isset($voteDetail['ip'])) {
                                    $userIp = $voteDetail['ip'];
                                }

                                // 收集需要查詢的食物ID
                                $foodIdsToFetch[$foodId] = true;

                                $voteDetails[] = [
                                    'date' => $date,
                                    'food_id' => $foodId,
                                    'food_title' => null, // 稍後填充
                                    'timestamp' => $timestamp,
                                    'datetime' => getTaipeiTime('Y-m-d H:i:s', $timestamp)
                                ];
                            }
                        }
                    }

                    // 暫存用戶數據
                    $userVoteDetails[$userId] = [
                        'user_data' => [
                            'user_id' => $userId,
                            'vote_count' => $voteCount,
                            'user_email' => $memberData['email'] ?? $userEmail,
                            'user_ip' => $memberData['last_ip'] ?? $userIp,
                            'first_login_time' => $memberData['first_login_time'] ?? '',
                            'last_login_time' => $memberData['last_login_time'] ?? '',
                            'login_count' => isset($memberData['login_count']) ? (int)$memberData['login_count'] : 0,
                            'data_source' => 'votes:user:* (補充)',
                            'has_votes_collection' => true,
                            'votes_details' => $voteDetails,
                            'has_member_data' => !empty($memberData)
                        ],
                        'vote_details' => $voteDetails
                    ];
                }
            }

            // 第二輪：批量獲取食物標題
            if (!empty($foodIdsToFetch)) {
                $pipeline = $redis->pipeline();
                $foodIds = array_keys($foodIdsToFetch);

                foreach ($foodIds as $foodId) {
                    $pipeline->hget("food:{$foodId}", 'title');
                }

                $foodTitleResults = $pipeline->execute();

                // 建立食物標題映射
                $foodTitleMap = [];
                foreach ($foodIds as $index => $foodId) {
                    $foodTitleMap[$foodId] = $foodTitleResults[$index] ?: '未知食物';
                }

                // 更新用戶投票詳情中的食物標題
                foreach ($userVoteDetails as $userId => &$userData) {
                    foreach ($userData['vote_details'] as &$voteDetail) {
                        if (isset($foodTitleMap[$voteDetail['food_id']])) {
                            $voteDetail['food_title'] = $foodTitleMap[$voteDetail['food_id']];
                        }
                    }
                    // 更新用戶數據中的投票詳情
                    $userData['user_data']['votes_details'] = $userData['vote_details'];
                }
            }

            // 將處理好的用戶數據添加到結果中
            foreach ($userVoteDetails as $userData) {
                $users[] = $userData['user_data'];
            }
        }

        // 2. 檢查其他數據源
        $otherUsersSources['user_vote_log_keys'] = count($redis->keys("user_vote_log:*"));
        $otherUsersSources['votes_daily_keys'] = count($redis->keys("votes:daily:*"));
        $otherUsersSources['has_vote_log'] = $redis->exists("vote_log");

        // 按投票數量降序排序
        usort($users, function ($a, $b) {
            return $b['vote_count'] - $a['vote_count'];
        });

        sendJsonResponse(true, "獲取用戶列表成功", [
            'users' => $users,
            'total_users' => count($users),
            'data_sources' => $dataSources,
            'source_stats' => [
                'members_index' => count($memberIds),
                'other_sources' => $otherUsersSources
            ],
            'progress_info' => $progress
        ]);
    } catch (Exception $e) {
        error_log("獲取用戶列表失敗: " . $e->getMessage());
        sendJsonResponse(false, "獲取用戶列表失敗: " . $e->getMessage(), null, 500);
    }
}

// 獲取可疑IP列表
function getSuspiciousIps($redis)
{
    try {
        // 獲取閾值參數，預設為10
        $threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 10;

        // 用於存儲IP統計
        $ipStats = [];

        // 1. 從會員數據中收集IP信息
        $memberKeys = $redis->keys("member:*");

        if (empty($memberKeys)) {
            sendJsonResponse(true, "沒有找到會員數據", []);
            return;
        }

        // 使用 PIPE 批量獲取會員數據
        $pipe = $redis->pipeline();
        $memberIds = [];

        foreach ($memberKeys as $memberKey) {
            if (preg_match('/member:([^:]+)$/', $memberKey, $matches)) {
                $memberIds[] = $matches[1];
                $pipe->hgetall($memberKey);
            }
        }

        $memberDataList = $pipe->execute();

        // 收集需要查詢投票數的用戶ID
        $userVoteKeys = [];
        $memberDataMap = [];

        for ($i = 0; $i < count($memberIds); $i++) {
            $memberId = $memberIds[$i];
            $memberData = $memberDataList[$i];

            if (isset($memberData['last_ip']) && !empty($memberData['last_ip'])) {
                $memberDataMap[$memberId] = $memberData;
                $userVoteKeys[] = "votes:user:{$memberId}";
            }
        }

        // 使用 PIPE 批量獲取投票數
        if (!empty($userVoteKeys)) {
            $pipe = $redis->pipeline();
            foreach ($userVoteKeys as $voteKey) {
                $pipe->exists($voteKey);
            }
            $voteExistsResults = $pipe->execute();

            $pipe = $redis->pipeline();
            for ($i = 0; $i < count($userVoteKeys); $i++) {
                if ($voteExistsResults[$i]) {
                    $pipe->zcard($userVoteKeys[$i]);
                } else {
                    $pipe->eval("return 0", 0); // 返回0作為占位符
                }
            }
            $voteCountResults = $pipe->execute();

            // 處理數據並構建IP統計
            $keyIndex = 0;
            foreach ($memberDataMap as $memberId => $memberData) {
                $ip = $memberData['last_ip'];
                $voteCount = $voteExistsResults[$keyIndex] ? $voteCountResults[$keyIndex] : 0;

                if (!isset($ipStats[$ip])) {
                    $ipStats[$ip] = [
                        'ip' => $ip,
                        'user_count' => 0,
                        'total_votes' => 0,
                        'users' => []
                    ];
                }

                $ipStats[$ip]['users'][] = [
                    'user_id' => $memberId,
                    'user_email' => $memberData['email'] ?? '',
                    'vote_count' => $voteCount
                ];

                $ipStats[$ip]['user_count']++;
                $ipStats[$ip]['total_votes'] += $voteCount;
                $keyIndex++;
            }
        }

        // 2. 過濾出可疑的IP（用戶數超過閾值的IP）
        $suspiciousIps = array_filter($ipStats, function ($stat) use ($threshold) {
            return $stat['user_count'] >= $threshold;
        });

        // 3. 按用戶數量降序排序
        uasort($suspiciousIps, function ($a, $b) {
            return $b['user_count'] - $a['user_count'];
        });

        sendJsonResponse(true, "獲取可疑IP列表成功", array_values($suspiciousIps));
    } catch (Exception $e) {
        error_log("獲取可疑IP列表失敗: " . $e->getMessage());
        sendJsonResponse(false, "獲取可疑IP列表失敗: " . $e->getMessage(), null, 500);
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

// 獲取Redis連接
$redis = getRedisConnection(true);
if (!$redis) {
    sendJsonResponse(false, "無法連接到Redis服務", null, 500);
    exit;
}

// 處理請求
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'vote_logs':
        getVoteLogs($redis);
        break;
    case 'user_vote_history':
        $userId = $_GET['user_id'] ?? null;
        if (!$userId) {
            sendJsonResponse(false, "缺少用戶ID參數", null, 400);
            break;
        }
        getUserVoteLogs($redis, $userId, 1, 100, null, null, null, null);
        break;
    case 'users':
        getAllUsers($redis);
        break;
    case 'suspicious_ips':
        getSuspiciousIps($redis);
        break;
    default:
        sendJsonResponse(false, "請指定有效的action參數", null, 400);
        break;
}
?>