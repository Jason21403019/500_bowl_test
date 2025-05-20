<?php

// 引入CORS設置和Redis連接
require_once 'cors.php';
require_once 'redis.php';
require_once 'timezone.php'; // 引入台北時間設定

// 設置內容類型為JSON
header('Content-Type: application/json; charset=utf-8');

// 定義全域變數
$today = getTaipeiTime('Y-m-d');

// 獲取用戶投票記錄
function getVoteLogs($redis)
{
    // 獲取請求參數
    $userId = $_GET['user_id'] ?? null;
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $startDate = $_GET['start_date'] ? convertToTaipeiTime($_GET['start_date']) : null;
    $endDate = $_GET['end_date'] ? convertToTaipeiTime($_GET['end_date']) : null;
    $format = $_GET['format'] ?? 'grouped'; // 新增格式參數，預設為分組顯示
    $searchKeyword = $_GET['search'] ?? null; // 新增搜尋關鍵字參數
    $bookId = $_GET['book_id'] ?? null; // 新增書籍ID過濾參數
    $sortBy = $_GET['sort_by'] ?? 'date'; // 新增排序方式參數
    $sortOrder = $_GET['sort_order'] ?? 'desc'; // 新增排序順序參數

    // 如果指定了用戶ID，直接從該用戶的專屬日誌獲取記錄
    if ($userId) {
        return getUserVoteLogs($redis, $userId, $page, $limit, $startDate, $endDate, $searchKeyword, $bookId);
    }

    // 計算分頁偏移
    $offset = ($page - 1) * $limit;

    // 獲取投票日誌總長度
    $voteLogKey = "vote_log";
    $totalLogs = $redis->llen($voteLogKey);

    // 獲取投票日誌
    $logs = [];
    $dataSources = [];

    // 1. 優先從用戶投票有序集合中獲取數據 (主要數據來源)
    $userVotesKeys = $redis->keys("votes:user:*");
    if (!empty($userVotesKeys)) {
        $dataSources[] = 'votes:user:*';

        foreach ($userVotesKeys as $key) {
            preg_match('/votes:user:(.+)/', $key, $matches);
            $uid = $matches[1] ?? 'unknown';

            // 獲取用戶信息
            $memberKey = "member:{$uid}";
            $memberData = $redis->exists($memberKey) ? $redis->hgetall($memberKey) : [];
            $userEmail = $memberData['email'] ?? '';
            $userIp = $memberData['last_ip'] ?? '';

            $entries = $redis->zrange($key, 0, -1, 'WITHSCORES');
            foreach ($entries as $voteKey => $timestamp) {
                // 解析投票記錄 (格式是 date:bookId:voteDetailJson)
                if (strpos($voteKey, ':') !== false) {
                    $parts = explode(':', $voteKey, 3);
                    if (count($parts) >= 3) {
                        $date = $parts[0];
                        $bookId = $parts[1];
                        $voteDetailJson = $parts[2];

                        // 處理JSON格式問題 - 嘗試修復不完整的JSON
                        if (substr($voteDetailJson, 0, 1) !== '{') {
                            $voteDetailJson = '{' . $voteDetailJson;
                        }
                        if (substr($voteDetailJson, -1) !== '}') {
                            $voteDetailJson .= '}';
                        }

                        // 嘗試解析 JSON
                        $detail = json_decode($voteDetailJson, true);
                        if ($detail) {
                            // 獲取書籍標題
                            $bookTitle = $detail['book_title'] ?? null;
                            if (!$bookTitle && !empty($bookId)) {
                                $bookTitle = $redis->hget("book:{$bookId}", 'title') ?: '未知書籍';
                            }

                            // 確保使用原始投票時間
                            $voteTimestamp = isset($detail['timestamp']) ? (int)$detail['timestamp'] : $timestamp;

                            // 構建標準格式的日誌條目
                            $logEntry = [
                                'user_id' => $uid,
                                'book_id' => $bookId,
                                'book_title' => $bookTitle ?? '未知書籍',
                                'timestamp' => $voteTimestamp,
                                'datetime' => convertToTaipeiTime($voteTimestamp, 'Y-m-d H:i:s'),
                                'date' => $date,
                                'user_ip' => $detail['ip'] ?? $userIp,
                                'user_email' => $detail['email'] ?? $userEmail,
                                'data_source' => 'votes:user'
                            ];
                            $logs[] = $logEntry;
                        }
                    }
                }
            }
        }
    }

    // 2. 如果沒有獲取到足夠的數據，嘗試從投票日誌獲取 (備用數據來源)
    if (empty($logs) && $redis->exists($voteLogKey)) {
        $dataSources[] = 'vote_log';

        $logEntries = $redis->lrange($voteLogKey, 0, -1);
        foreach ($logEntries as $logJson) {
            $log = json_decode($logJson, true);
            if ($log && isset($log['user_id'])) {
                // 標記數據來源
                $log['data_source'] = 'vote_log';
                $logs[] = $log;
            }
        }
    }

    // 3. 嘗試從用戶專屬日誌獲取 (可選備用數據來源)
    if (empty($logs)) {
        $userLogKeys = $redis->keys("user_vote_log:*");
        if (!empty($userLogKeys)) {
            $dataSources[] = 'user_vote_log:*';

            foreach ($userLogKeys as $key) {
                preg_match('/user_vote_log:(.+)/', $key, $matches);
                $uid = $matches[1] ?? 'unknown';

                $entries = $redis->lrange($key, 0, -1);
                foreach ($entries as $logJson) {
                    $log = json_decode($logJson, true);
                    if ($log && isset($log['user_id'])) {
                        // 標記數據來源
                        $log['data_source'] = 'user_vote_log';
                        $logs[] = $log;
                    }
                }
            }
        }
    }

    // 4. 從每日投票記錄補充數據 (確保完整性)
    $dailyVoteKeys = $redis->keys("votes:daily:*:*");
    if (!empty($dailyVoteKeys)) {
        $dataSources[] = 'votes:daily:*';

        $processedDailyVotes = [];
        foreach ($dailyVoteKeys as $key) {
            if (preg_match('/votes:daily:(.+?):(\d{4}-\d{2}-\d{2})$/', $key, $matches)) {
                $uid = $matches[1];
                $date = $matches[2];

                // 避免重複處理相同的用戶和日期
                $uniqueKey = "{$uid}:{$date}";
                if (isset($processedDailyVotes[$uniqueKey])) continue;
                $processedDailyVotes[$uniqueKey] = true;

                // 獲取用戶信息
                $memberKey = "member:{$uid}";
                $memberData = $redis->exists($memberKey) ? $redis->hgetall($memberKey) : [];
                $userEmail = $memberData['email'] ?? '';
                $userIp = $memberData['last_ip'] ?? '';

                // 獲取用戶當天投票的所有書籍
                $votedBooks = $redis->smembers($key);
                foreach ($votedBooks as $bookId) {
                    // 檢查是否已經在日誌中存在
                    $exists = false;
                    foreach ($logs as $existingLog) {
                        if (
                            $existingLog['user_id'] === $uid &&
                            $existingLog['book_id'] === $bookId &&
                            $existingLog['date'] === $date
                        ) {
                            $exists = true;
                            break;
                        }
                    }

                    if (!$exists) {
                        // 獲取書籍標題
                        $bookTitle = $redis->hget("book:{$bookId}", 'title') ?: '未知書籍';

                        // 構建時間戳 (使用當天的凌晨時間)
                        $voteTimestamp = strtotime($date);

                        // 添加補充的投票記錄
                        $logs[] = [
                            'user_id' => $uid,
                            'book_id' => $bookId,
                            'book_title' => $bookTitle,
                            'timestamp' => $voteTimestamp,
                            'datetime' => convertToTaipeiTime($voteTimestamp, 'Y-m-d H:i:s'),
                            'date' => $date,
                            'user_ip' => $userIp,
                            'user_email' => $userEmail,
                            'data_source' => 'votes:daily (補充)'
                        ];
                    }
                }
            }
        }
    }

    // 過濾並處理日誌
    $filteredLogs = [];
    $groupedByUser = []; // 按用戶ID分組的數據
    $uniqueVotes = []; // 用於去重

    foreach ($logs as $log) {
        if (!$log || !isset($log['user_id']) || !isset($log['book_id']) || !isset($log['date'])) continue;

        // 創建唯一識別碼以去除重複記錄
        $uniqueKey = "{$log['user_id']}:{$log['book_id']}:{$log['date']}";
        if (isset($uniqueVotes[$uniqueKey])) continue;
        $uniqueVotes[$uniqueKey] = true;

        // 過濾指定用戶
        if ($userId && $log['user_id'] !== $userId) {
            continue;
        }

        // 過濾指定書籍ID
        if ($bookId && $log['book_id'] !== $bookId) {
            continue;
        }

        // 過濾時間範圍
        if ($startDate && (!isset($log['date']) || strtotime($log['date']) < strtotime($startDate))) {
            continue;
        }

        if ($endDate && (!isset($log['date']) || strtotime($log['date']) > strtotime($endDate))) {
            continue;
        }

        // 搜尋關鍵字 (匹配用戶ID、用戶Email、書籍標題)
        if ($searchKeyword) {
            $keyword = strtolower($searchKeyword);
            $matched = false;

            // 搜尋用戶ID
            if (isset($log['user_id']) && stripos($log['user_id'], $keyword) !== false) {
                $matched = true;
            }
            // 搜尋用戶Email
            else if (isset($log['user_email']) && stripos($log['user_email'], $keyword) !== false) {
                $matched = true;
            }
            // 搜尋書籍標題
            else if (isset($log['book_title']) && stripos($log['book_title'], $keyword) !== false) {
                $matched = true;
            }
            // 搜尋IP地址
            else if (isset($log['user_ip']) && stripos($log['user_ip'], $keyword) !== false) {
                $matched = true;
            }

            if (!$matched) {
                continue;
            }
        }

        // 確保欄位一致性 (向後兼容)
        if (!isset($log['user_ip']) && isset($log['ip'])) {
            $log['user_ip'] = $log['ip'];
        }

        if (!isset($log['user_email']) && isset($log['email'])) {
            $log['user_email'] = $log['email'];
        }

        // 添加到過濾後的日誌
        $filteredLogs[] = $log;

        // 按用戶ID分組
        $uid = $log['user_id'];
        if (!isset($groupedByUser[$uid])) {
            $groupedByUser[$uid] = [
                'user_id' => $uid,
                'user_email' => $log['user_email'] ?? '',
                'user_ip' => $log['user_ip'] ?? '',
                'votes' => []
            ];
        } else {
            // 如果之前沒有電子郵件或IP，但現在有了，更新它們
            if (empty($groupedByUser[$uid]['user_email']) && !empty($log['user_email'])) {
                $groupedByUser[$uid]['user_email'] = $log['user_email'];
            }
            if (empty($groupedByUser[$uid]['user_ip']) && !empty($log['user_ip'])) {
                $groupedByUser[$uid]['user_ip'] = $log['user_ip'];
            }
        }

        // 添加投票信息到用戶分組中
        $voteInfo = [
            'book_id' => $log['book_id'],
            'book_title' => $log['book_title'],
            'timestamp' => $log['timestamp'] ?? time(),
            'datetime' => $log['datetime'] ?? getTaipeiTime('Y-m-d H:i:s'),
            'date' => $log['date'],
            'data_source' => $log['data_source'] ?? 'unknown'
        ];
        $groupedByUser[$uid]['votes'][] = $voteInfo;
    }

    // 根據排序方式對過濾後的日誌進行排序
    if ($sortBy === 'date') {
        usort($filteredLogs, function ($a, $b) use ($sortOrder) {
            $dateA = isset($a['date']) ? strtotime($a['date']) : 0;
            $dateB = isset($b['date']) ? strtotime($b['date']) : 0;
            $result = $dateA - $dateB;
            return $sortOrder === 'asc' ? $result : -$result;
        });
    } elseif ($sortBy === 'user_id') {
        usort($filteredLogs, function ($a, $b) use ($sortOrder) {
            $result = strcmp($a['user_id'], $b['user_id']);
            return $sortOrder === 'asc' ? $result : -$result;
        });
    } elseif ($sortBy === 'book_title') {
        usort($filteredLogs, function ($a, $b) use ($sortOrder) {
            $titleA = isset($a['book_title']) ? $a['book_title'] : '';
            $titleB = isset($b['book_title']) ? $b['book_title'] : '';
            $result = strcmp($titleA, $titleB);
            return $sortOrder === 'asc' ? $result : -$result;
        });
    }

    // 應用分頁過濾
    $paginatedLogs = array_slice($filteredLogs, $offset, $limit);

    // 按日期分組
    $votesByDate = [];
    foreach ($paginatedLogs as $log) {
        if (!isset($log['date'])) continue;
        $date = $log['date'];
        if (!isset($votesByDate[$date])) {
            $votesByDate[$date] = [];
        }
        $votesByDate[$date][] = $log;
    }

    // 日期遞減排序
    krsort($votesByDate);

    // 將分組數據轉為數組
    $groupedData = array_values($groupedByUser);

    // 為每個分組添加投票數量
    foreach ($groupedData as &$user) {
        $user['vote_count'] = count($user['votes']);
    }

    // 準備分頁信息
    $pagination = [
        'total' => count($filteredLogs),
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil(count($filteredLogs) / $limit)
    ];

    // 發送響應，根據請求格式返回不同結構的數據
    if ($format === 'grouped') {
        sendJsonResponse(true, "獲取分組投票記錄成功", [
            'grouped_by_user' => $groupedData,
            'pagination' => $pagination,
            'filters' => [
                'user_id' => $userId,
                'book_id' => $bookId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $searchKeyword,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ],
            'data_sources' => $dataSources
        ]);
    } else {
        sendJsonResponse(true, "獲取投票記錄成功", [
            'votes' => $paginatedLogs,
            'votes_by_date' => $votesByDate,
            'pagination' => $pagination,
            'filters' => [
                'user_id' => $userId,
                'book_id' => $bookId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $searchKeyword,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ],
            'data_sources' => $dataSources
        ]);
    }
}

// 獲取用戶投票日誌 - 優化版本，先獲取會員資料再關聯投票
function getUserVoteLogs($redis, $userId, $page, $limit, $startDate, $endDate, $searchKeyword, $bookId)
{
    // 計算分頁偏移
    $offset = ($page - 1) * $limit;

    // 1. 獲取用戶信息
    $memberKey = "member:{$userId}";
    $memberExists = $redis->exists($memberKey);
    $memberData = $memberExists ? $redis->hgetall($memberKey) : [];

    // 2. 獲取折扣卡信息
    $memberDiscountPinKey = "member:discount_pin:{$userId}";
    $discountPinExists = $redis->exists($memberDiscountPinKey);
    $discountPinData = $discountPinExists ? $redis->hgetall($memberDiscountPinKey) : null;

    // 3. 獲取用戶投票集合
    $userVotesKey = "votes:user:{$userId}";
    $userVotesExists = $redis->exists($userVotesKey);
    $userVotesCount = $userVotesExists ? $redis->zcard($userVotesKey) : 0;

    // 4. 獲取投票詳情
    $logs = [];
    $dataSourceInfo = [];

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
                    $bookIdFromKey = $parts[1];
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
                        // 從快取中獲取書籍標題
                        $bookTitle = $detail['book_title'] ?? null;
                        if (!$bookTitle && !empty($bookIdFromKey)) {
                            $bookTitle = $redis->hget("book:{$bookIdFromKey}", 'title') ?: '未知書籍';
                        }

                        // 使用原始記錄中的時間戳，如果有的話
                        $actualTimestamp = isset($detail['timestamp']) ? (int)$detail['timestamp'] : $timestamp;

                        // 構建日誌條目
                        $logEntry = [
                            'user_id' => $userId,
                            'book_id' => $bookIdFromKey,
                            'book_title' => $bookTitle,
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

    // 直接使用所有日誌，不做過濾
    $filteredLogs = $logs;
    $votesByDate = [];

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

// 獲取所有用戶列表 - 重構版本，優先從會員數據獲取
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

            // 先獲取所有用戶基本信息並創建映射
            $userMap = [];
            foreach ($memberIds as $memberId) {
                $progress['processed']++;

                if (!in_array($memberId, $userIds)) {
                    $userIds[] = $memberId;
                    $memberKey = "member:{$memberId}";

                    // 檢查會員資料是否存在
                    if ($redis->exists($memberKey)) {
                        $memberData = $redis->hgetall($memberKey);

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

            // 然後為每個用戶獲取投票詳情 (批量處理)
            $progress['vote_collections_checked'] = 0;
            foreach ($userMap as $userId => &$userData) {
                $progress['vote_collections_checked']++;

                // 檢查用戶是否有專屬投票集合
                $userVotesKey = "votes:user:{$userId}";
                if ($redis->exists($userVotesKey)) {
                    $userData['has_votes_collection'] = true;

                    // 獲取投票計數
                    $voteCount = $redis->zcard($userVotesKey);

                    // 只有當投票計數不一致時才更新
                    if ($voteCount != $userData['vote_count']) {
                        $userData['vote_count'] = $voteCount;
                        $userData['data_source'] .= ', votes:user:*';
                    }

                    // 可選：獲取幾個最近的投票詳情 (僅獲取有限數量以節省資源)
                    $recentVotes = $redis->zrevrange($userVotesKey, 0, 2, 'WITHSCORES');
                    if (!empty($recentVotes)) {
                        foreach ($recentVotes as $voteKey => $timestamp) {
                            // 解析投票記錄 (格式是 date:bookId:voteDetailJson)
                            $parts = explode(':', $voteKey, 3);
                            if (count($parts) >= 3) {
                                $date = $parts[0];
                                $bookId = $parts[1];
                                $voteDetailJson = $parts[2];

                                // 嘗試從投票詳情獲取實際時間戳
                                $actualTimestamp = $timestamp;
                                $detailData = json_decode($voteDetailJson, true);
                                if ($detailData && isset($detailData['timestamp'])) {
                                    $actualTimestamp = (int)$detailData['timestamp'];
                                }

                                // 獲取書籍標題
                                $bookTitle = $redis->hget("book:{$bookId}", 'title') ?: '未知書籍';

                                $userData['votes_details'][] = [
                                    'date' => $date,
                                    'book_id' => $bookId,
                                    'book_title' => $bookTitle,
                                    'timestamp' => $actualTimestamp,
                                    'datetime' => convertToTaipeiTime($actualTimestamp, 'Y-m-d H:i:s')
                                ];
                            }
                        }
                    }
                }

                // 檢查每日投票記錄
                $todayVotesKey = "votes:daily:{$userId}:" . getTaipeiTime('Y-m-d');
                if ($redis->exists($todayVotesKey)) {
                    $userData['has_voted_today'] = true;
                    $userData['today_vote_count'] = $redis->scard($todayVotesKey);
                    $userData['today_voted_books'] = $redis->smembers($todayVotesKey);
                    $userData['data_source'] .= ', votes:daily:*';
                } else {
                    $userData['has_voted_today'] = false;
                    $userData['today_vote_count'] = 0;
                    $userData['today_voted_books'] = [];
                }
            }

            // 轉換用戶映射為陣列
            $users = array_values($userMap);
        }

        // 尋找不在會員索引中的用戶 (從投票記錄中補充)
        $otherUsersSources = [];

        // 1. 從 votes:user:* 有序集合獲取用戶
        $pattern = "votes:user:*";
        $voteUserKeys = $redis->keys($pattern);
        $otherUsersSources['votes_user_keys'] = count($voteUserKeys);

        if (!empty($voteUserKeys)) {
            $dataSources[] = 'votes:user:* (補充)';

            foreach ($voteUserKeys as $key) {
                preg_match('/votes:user:(.+)/', $key, $matches);
                if (isset($matches[1])) {
                    $userId = $matches[1];
                    if (!in_array($userId, $userIds)) {
                        $userIds[] = $userId;

                        // 檢查是否已有會員數據
                        $memberKey = "member:{$userId}";
                        $memberData = $redis->exists($memberKey) ? $redis->hgetall($memberKey) : [];

                        // 獲取投票計數和樣本
                        $voteCount = $redis->zcard($key);
                        $voteRecords = $redis->zrevrange($key, 0, 2, 'WITHSCORES'); // 只獲取最近幾條

                        // 從投票記錄中提取用戶信息
                        $userEmail = '';
                        $userIp = '';
                        $voteDetails = [];

                        foreach ($voteRecords as $voteKey => $timestamp) {
                            $parts = explode(':', $voteKey, 3);
                            if (count($parts) >= 3) {
                                $date = $parts[0];
                                $bookId = $parts[1];
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

                                    // 獲取書籍標題
                                    $bookTitle = $redis->hget("book:{$bookId}", 'title') ?: '未知書籍';

                                    $voteDetails[] = [
                                        'date' => $date,
                                        'book_id' => $bookId,
                                        'book_title' => $bookTitle,
                                        'timestamp' => $timestamp,
                                        'datetime' => getTaipeiTime('Y-m-d H:i:s', $timestamp)
                                    ];
                                }
                            }
                        }

                        // 使用從會員數據或投票記錄中獲取的信息
                        $users[] = [
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
                        ];
                    }
                }
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
    default:
        sendJsonResponse(false, "請指定有效的action參數", null, 400);
        break;
}
