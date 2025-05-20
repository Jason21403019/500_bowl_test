<?php
// 設定預設時區為台北時間
date_default_timezone_set('Asia/Taipei');

/**
 * 取得當前台北時間
 * 
 * @param string $format 日期時間格式
 * @return string 格式化後的台北時間
 */
function getTaipeiTime($format = 'Y-m-d H:i:s') {
    return date($format);
}

/**
 * 將任何時間轉換為台北時間
 * 
 * @param string|int|DateTime $time 要轉換的時間
 * @param string $format 輸出格式
 * @return string 格式化後的台北時間
 */
function convertToTaipeiTime($time, $format = 'Y-m-d H:i:s') {
    if (is_numeric($time)) {
        // 如果是時間戳記
        $dt = new DateTime('@' . $time);
        $dt->setTimezone(new DateTimeZone('Asia/Taipei'));
        return $dt->format($format);
    } else if ($time instanceof DateTime) {
        // 如果是 DateTime 物件
        $dt = clone $time;
        $dt->setTimezone(new DateTimeZone('Asia/Taipei'));
        return $dt->format($format);
    } else {
        // 如果是日期字串
        $dt = new DateTime($time);
        $dt->setTimezone(new DateTimeZone('Asia/Taipei'));
        return $dt->format($format);
    }
}
?>
