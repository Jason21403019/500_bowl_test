<?php
// 設置 header
header('Content-Type: text/html; charset=utf-8');
// 設置時區為台北時間

// 引入 Redis 連接工具和台北時間設定
require_once 'redis.php';
require_once 'timezone.php'; // 引入台北時間設定

// 獲取 cookie 資訊
$account = isset($_COOKIE['udnmember']) ? $_COOKIE['udnmember'] : '';

// 如果 udnmember 沒有值，嘗試從 udnland 獲取
if (empty($account) && isset($_COOKIE['udnland'])) {
    $account = $_COOKIE['udnland'];
}

// 獲取 IP 地址的函數
function get_ip()
{
    $ip_headers = [
        'HTTP_AKACIP',
        'HTTP_L7CIP',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];

    foreach ($ip_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = filter_var($_SERVER[$header], FILTER_VALIDATE_IP);
            if ($ip !== false) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

// 添加會員數據同步函數
function syncMemberData($memberId)
{
    // 檢查 Cookie 取得會員信箱
    $email = null;
    $apiUrl = "https://umapi.udn.com/member/wbs/MemberUm2Check";

    // 優先使用 udnmember，如果不存在則嘗試使用 udnland
    $udnmember = !empty($_COOKIE['udnmember']) ? $_COOKIE['udnmember'] : $_COOKIE['udnland'] ?? '';
    $um2 = $_COOKIE['um2'] ?? '';

    // 如果有必要的 cookie 值
    if (!empty($udnmember) && !empty($um2)) {
        $um2Encoded = urlencode($um2);

        // 準備 API 請求數據 - 更新配置
        $data = [
            'account' => $udnmember,
            'um2' => $um2Encoded,
            'json' => 'Y',
            'site' => 'bd_500bowls_vote2025',  // 網站代碼，限制20字元
            'check_ts' => 'S'        // 檢查cookie時效是否超過30分鐘
        ];

        // 從會員系統 API 獲取資料
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        // 解析 API 回應
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // 檢查 API 響應狀態
            if (isset($data['response']) && isset($data['response']['status']) && $data['response']['status'] === 'success') {
                if (isset($data['response']['email'])) {
                    $email = filter_var($data['response']['email'], FILTER_SANITIZE_EMAIL);
                }
                // 驗證成功的狀態
                $verified = true;
            } else {
                // 驗證失敗
                $verified = false;
                error_log("Member verification failed: " . json_encode($data));
            }
        } else {
            $verified = false;
            error_log("Failed to parse member API response: " . $response);
        }
    } else {
        $verified = false;
    }

    // 如果API請求失敗，嘗試從 fg_mail cookie 獲取
    if (empty($email) && isset($_COOKIE['fg_mail'])) {
        $email = filter_var(urldecode($_COOKIE['fg_mail']), FILTER_SANITIZE_EMAIL);
    }

    // 記錄會員信箱到日誌
    error_log("Member email fetched: " . ($email ?: 'NULL') . " for ID: " . $memberId);

    return [
        'member_id' => $memberId,
        'email' => $email,
        'verified' => $verified ?? false
    ];
}

$um2 = isset($_COOKIE['um2']) ? $_COOKIE['um2'] : '';

// 獲取參數，檢查是否要以 JSON 格式回傳
$json_response = isset($_GET['json']) && strtoupper($_GET['json']) === 'Y';

// 使用新的同步函數驗證會員
$is_valid = false;
$email = "";
$ip = get_ip(); // 獲取 IP 地址

if (!empty($account)) {
    // 使用 syncMemberData 函數獲取會員資料
    $member_data = syncMemberData($account);
    $is_valid = $member_data['verified'];
    $email = $member_data['email'] ?? "";

    // 如果會員驗證成功，將會員資料儲存到 Redis
    if ($is_valid) {
        saveOrUpdateMember($account, $email, $ip);
    }
}

/**
 * 將會員資料儲存或更新到 Redis
 * @param string $memberId 會員ID
 * @param string $email 會員電子郵件
 * @param string $ip 會員IP
 * @return boolean 操作是否成功
 */
function saveOrUpdateMember($memberId, $email, $ip)
{
    // 獲取 Redis 連接
    $redis = getRedisConnection(true);
    if (!$redis) {
        error_log("儲存會員資料失敗：無法連接 Redis");
        return false;
    }

    try {
        $memberKey = "member:{$memberId}";
        $currentTime = time();
        $currentDatetime = getTaipeiTime('Y-m-d H:i:s');

        // 檢查會員是否已存在
        $exists = $redis->exists($memberKey);

        // 取得舊有資料
        if ($exists) {
            $oldData = $redis->hgetall($memberKey);
            $lastLoginTime = isset($oldData['last_login_time']) ? $oldData['last_login_time'] : '';
            $firstLoginTime = isset($oldData['first_login_time']) ? $oldData['first_login_time'] : $currentDatetime;
        } else {
            $firstLoginTime = $currentDatetime;
            $lastLoginTime = '';
        }

        // 準備會員資料
        $memberData = [
            'id' => $memberId,
            'email' => $email,
            'last_ip' => $ip,
            'first_login_time' => $firstLoginTime,
            'last_login_time' => $currentDatetime,
            'updated_at' => $currentDatetime
        ];

        // 儲存會員資料到 Hash 結構
        $redis->hmset($memberKey, $memberData);

        // 將會員ID加入索引集合
        $redis->sadd('members:index', $memberId);

        error_log("會員資料已更新: $memberId, Email: $email, IP: $ip");
        return true;
    } catch (Exception $e) {
        error_log("儲存會員資料發生錯誤: " . $e->getMessage());
        return false;
    }
}

// 根據驗證結果和回傳格式設置回應
if ($json_response) {
    // JSON 格式回應
    header('Content-Type: application/json');

    if ($is_valid) {
        $result = [
            "response" => [
                "udnmember" => $account,
                "email" => $email,
                "ip" => $ip,
                "status" => "success"
            ]
        ];
    } else {
        $result = [
            "response" => [
                "status" => "fail"
            ]
        ];
    }

    echo json_encode($result);
} else {
    // 修改純文字回應，包含更多資訊
    if ($is_valid) {
        echo "驗證狀態: 成功\n";
        echo "會員帳號: " . $account . "\n";
        echo "會員郵箱: " . $email . "\n";
        echo "IP 地址: " . $ip . "\n";
    } else {
        echo "驗證狀態: 失敗\n";
        echo "IP 地址: " . $ip . "\n";
    }
}
