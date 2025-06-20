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
        ['id' => '1', 'votes' => '0', 'title' => '擔仔麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/1.webp&nt=1&v=20250620'],
        ['id' => '2', 'votes' => '0', 'title' => '滷肉飯 / 魯肉飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/2.webp&nt=1&v=20250620'],
        ['id' => '3', 'votes' => '0', 'title' => '米粉湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/3.webp&nt=1&v=20250620'],
        ['id' => '4', 'votes' => '0', 'title' => '黑白切', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/4.webp&nt=1&v=20250620'],
        ['id' => '5', 'votes' => '0', 'title' => '蔥油拌麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/5.webp&nt=1&v=20250620'],
        ['id' => '6', 'votes' => '0', 'title' => '肉燥飯 / 肉臊飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/6.webp&nt=1&v=20250620'],
        ['id' => '7', 'votes' => '0', 'title' => '臭豆腐', 'tags' => '炸物類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/7.webp&nt=1&v=20250620'],
        ['id' => '8', 'votes' => '0', 'title' => '羹湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/8.webp&nt=1&v=20250620'],
        ['id' => '9', 'votes' => '0', 'title' => '豬肝湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/9.webp&nt=1&v=20250620'],
        ['id' => '10', 'votes' => '0', 'title' => '燒餅', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/10.webp&nt=1&v=20250620'],
        ['id' => '11', 'votes' => '0', 'title' => '酥餅', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/11.webp&nt=1&v=20250620'],
        ['id' => '12', 'votes' => '0', 'title' => '胡椒餅', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/12.webp&nt=1&v=20250620'],
        ['id' => '13', 'votes' => '0', 'title' => '乾麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/13.webp&nt=1&v=20250620'],
        ['id' => '14', 'votes' => '0', 'title' => '潤餅', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/14.webp&nt=1&v=20250620'],
        ['id' => '15', 'votes' => '0', 'title' => '鴨肉麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/15.webp&nt=1&v=20250620'],
        ['id' => '16', 'votes' => '0', 'title' => '肉圓', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/16.webp&nt=1&v=20250620'],
        ['id' => '17', 'votes' => '0', 'title' => '牛肉麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/17.webp&nt=1&v=20250620'],
        ['id' => '18', 'votes' => '0', 'title' => '炕肉飯 / 爌肉飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/18.webp&nt=1&v=20250620'],
        ['id' => '19', 'votes' => '0', 'title' => '牛雜湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/19.webp&nt=1&v=20250620'],
        ['id' => '20', 'votes' => '0', 'title' => '火雞肉飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/20.webp&nt=1&v=20250620'],
        ['id' => '21', 'votes' => '0', 'title' => '牛肉湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/21.webp&nt=1&v=20250620'],
        ['id' => '22', 'votes' => '0', 'title' => '麻油豬肝', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/22.webp&nt=1&v=20250620'],
        ['id' => '23', 'votes' => '0', 'title' => '大腸包小腸', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/23.webp&nt=1&v=20250620'],
        ['id' => '24', 'votes' => '0', 'title' => '炭烤香腸', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/24.webp&nt=1&v=20250620'],
        ['id' => '25', 'votes' => '0', 'title' => '蔥油餅', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/25.webp&nt=1&v=20250620'],
        ['id' => '26', 'votes' => '0', 'title' => '米苔目', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/26.webp&nt=1&v=20250620'],
        ['id' => '27', 'votes' => '0', 'title' => '炸醬麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/27.webp&nt=1&v=20250620'],
        ['id' => '28', 'votes' => '0', 'title' => '油飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/28.webp&nt=1&v=20250620'],
        ['id' => '29', 'votes' => '0', 'title' => '雞腿飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/29.webp&nt=1&v=20250620'],
        ['id' => '30', 'votes' => '0', 'title' => '花生湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/30.webp&nt=1&v=20250620'],
        ['id' => '31', 'votes' => '0', 'title' => '炭烤玉米', 'tags' => '燒烤類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/31.webp&nt=1&v=20250620'],
        ['id' => '32', 'votes' => '0', 'title' => '咖哩麵包', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/32.webp&nt=1&v=20250620'],
        ['id' => '33', 'votes' => '0', 'title' => '炸春捲', 'tags' => '炸物類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/33.webp&nt=1&v=20250620'],
        ['id' => '34', 'votes' => '0', 'title' => '豆花', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/34.webp&nt=1&v=20250620'],
        ['id' => '35', 'votes' => '0', 'title' => '水煎包', 'tags' => '包子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/35.webp&nt=1&v=20250620'],
        ['id' => '36', 'votes' => '0', 'title' => '池上便當', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/36.webp&nt=1&v=20250620'],
        ['id' => '37', 'votes' => '0', 'title' => '愛玉冰', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/37.webp&nt=1&v=20250620'],
        ['id' => '38', 'votes' => '0', 'title' => '仙草凍 / 燒仙草', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/38.webp&nt=1&v=20250620'],
        ['id' => '39', 'votes' => '0', 'title' => '鍋燒麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/39.webp&nt=1&v=20250620'],
        ['id' => '40', 'votes' => '0', 'title' => '炸蚵嗲', 'tags' => '炸物類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/40.webp&nt=1&v=20250620'],
        ['id' => '41', 'votes' => '0', 'title' => '羊肉湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/41.webp&nt=1&v=20250620'],
        ['id' => '42', 'votes' => '0', 'title' => '肉粿', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/42.webp&nt=1&v=20250620'],
        ['id' => '43', 'votes' => '0', 'title' => '肉粽', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/43.webp&nt=1&v=20250620'],
        ['id' => '44', 'votes' => '0', 'title' => '涼麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/44.webp&nt=1&v=20250620'],
        ['id' => '45', 'votes' => '0', 'title' => '刈包', 'tags' => '包子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/45.webp&nt=1&v=20250620'],
        ['id' => '46', 'votes' => '0', 'title' => '餛飩湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/46.webp&nt=1&v=20250620'],
        ['id' => '47', 'votes' => '0', 'title' => '碳烤三明治', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/47.webp&nt=1&v=20250620'],
        ['id' => '48', 'votes' => '0', 'title' => '蘿蔔絲餅', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/48.webp&nt=1&v=20250620'],
        ['id' => '49', 'votes' => '0', 'title' => '蒸餃', 'tags' => '餃子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/49.webp&nt=1&v=20250620'],
        ['id' => '50', 'votes' => '0', 'title' => '糖醋排骨', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/50.webp&nt=1&v=20250620'],
        ['id' => '51', 'votes' => '0', 'title' => '紅糟肉', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/51.webp&nt=1&v=20250620'],
        ['id' => '52', 'votes' => '0', 'title' => '碗粿', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/52.webp&nt=1&v=20250620'],
        ['id' => '53', 'votes' => '0', 'title' => '涼圓', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/53.webp&nt=1&v=20250620'],
        ['id' => '54', 'votes' => '0', 'title' => '鮮肉包', 'tags' => '包子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/54.webp&nt=1&v=20250620'],
        ['id' => '55', 'votes' => '0', 'title' => '鴨肉飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/55.webp&nt=1&v=20250620'],
        ['id' => '56', 'votes' => '0', 'title' => '下水湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/56.webp&nt=1&v=20250620'],
        ['id' => '57', 'votes' => '0', 'title' => '腿庫飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/57.webp&nt=1&v=20250620'],
        ['id' => '58', 'votes' => '0', 'title' => '雞絲麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/58.webp&nt=1&v=20250620'],
        ['id' => '59', 'votes' => '0', 'title' => '卜肉', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/59.webp&nt=1&v=20250620'],
        ['id' => '60', 'votes' => '0', 'title' => '餡餅', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/60.webp&nt=1&v=20250620'],
        ['id' => '61', 'votes' => '0', 'title' => '油條', 'tags' => '炸物類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/61.webp&nt=1&v=20250620'],
        ['id' => '62', 'votes' => '0', 'title' => '麵線', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/62.webp&nt=1&v=20250620'],
        ['id' => '63', 'votes' => '0', 'title' => '排骨飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/63.webp&nt=1&v=20250620'],
        ['id' => '64', 'votes' => '0', 'title' => '滷豬腳', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/64.webp&nt=1&v=20250620'],
        ['id' => '65', 'votes' => '0', 'title' => '排骨酥', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/65.webp&nt=1&v=20250620'],
        ['id' => '66', 'votes' => '0', 'title' => '雞卷', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/66.webp&nt=1&v=20250620'],
        ['id' => '67', 'votes' => '0', 'title' => '糯米腸', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/67.webp&nt=1&v=20250620'],
        ['id' => '68', 'votes' => '0', 'title' => '蛋餅', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/68.webp&nt=1&v=20250620'],
        ['id' => '69', 'votes' => '0', 'title' => '甜不辣', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/69.webp&nt=1&v=20250620'],
        ['id' => '70', 'votes' => '0', 'title' => '韭菜盒子', 'tags' => '麵餅類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/70.webp&nt=1&v=20250620'],
        ['id' => '71', 'votes' => '0', 'title' => '菜包', 'tags' => '包子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/71.webp&nt=1&v=20250620'],
        ['id' => '72', 'votes' => '0', 'title' => '水餃', 'tags' => '餃子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/72.webp&nt=1&v=20250620'],
        ['id' => '73', 'votes' => '0', 'title' => '蚵仔煎', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/73.webp&nt=1&v=20250620'],
        ['id' => '74', 'votes' => '0', 'title' => '鹽水雞', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/74.webp&nt=1&v=20250620'],
        ['id' => '75', 'votes' => '0', 'title' => '滷味', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/75.webp&nt=1&v=20250620'],
        ['id' => '76', 'votes' => '0', 'title' => '滷雞腳', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/76.webp&nt=1&v=20250620'],
        ['id' => '77', 'votes' => '0', 'title' => '生煎包', 'tags' => '包子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/77.webp&nt=1&v=20250620'],
        ['id' => '78', 'votes' => '0', 'title' => '炒米粉', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/78.webp&nt=1&v=20250620'],
        ['id' => '79', 'votes' => '0', 'title' => '海鮮粥 / 海產粥', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/79.webp&nt=1&v=20250620'],
        ['id' => '80', 'votes' => '0', 'title' => '豬腸冬粉', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/80.webp&nt=1&v=20250620'],
        ['id' => '81', 'votes' => '0', 'title' => '小籠包', 'tags' => '包子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/81.webp&nt=1&v=20250620'],
        ['id' => '82', 'votes' => '0', 'title' => '紅油抄手', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/82.webp&nt=1&v=20250620'],
        ['id' => '83', 'votes' => '0', 'title' => '滷豆干', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/83.webp&nt=1&v=20250620'],
        ['id' => '84', 'votes' => '0', 'title' => '四神湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/84.webp&nt=1&v=20250620'],
        ['id' => '85', 'votes' => '0', 'title' => '榨菜肉絲麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/85.webp&nt=1&v=20250620'],
        ['id' => '86', 'votes' => '0', 'title' => '夜市牛排', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/86.webp&nt=1&v=20250620'],
        ['id' => '87', 'votes' => '0', 'title' => '飯糰', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/87.webp&nt=1&v=20250620'],
        ['id' => '88', 'votes' => '0', 'title' => '炸雞', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/88.webp&nt=1&v=20250620'],
        ['id' => '89', 'votes' => '0', 'title' => '雞排', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/89.webp&nt=1&v=20250620'],
        ['id' => '90', 'votes' => '0', 'title' => '酸辣湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/90.webp&nt=1&v=20250620'],
        ['id' => '91', 'votes' => '0', 'title' => '豬血糕 / 米血', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/91.webp&nt=1&v=20250620'],
        ['id' => '92', 'votes' => '0', 'title' => '拔絲地瓜', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/92.webp&nt=1&v=20250620'],
        ['id' => '93', 'votes' => '0', 'title' => '鴨舌', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/93.webp&nt=1&v=20250620'],
        ['id' => '94', 'votes' => '0', 'title' => '燒臘飯', 'tags' => '米飯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/94.webp&nt=1&v=20250620'],
        ['id' => '95', 'votes' => '0', 'title' => '米糕', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/95.webp&nt=1&v=20250620'],
        ['id' => '96', 'votes' => '0', 'title' => '茶碗蒸', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/96.webp&nt=1&v=20250620'],
        ['id' => '97', 'votes' => '0', 'title' => '鹹酥雞', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/97.webp&nt=1&v=20250620'],
        ['id' => '98', 'votes' => '0', 'title' => '客家炒粄條', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/98.webp&nt=1&v=20250620'],
        ['id' => '99', 'votes' => '0', 'title' => '客家鹹湯圓', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/99.webp&nt=1&v=20250620'],
        ['id' => '100', 'votes' => '0', 'title' => '木瓜牛奶', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/100.webp&nt=1&v=20250620'],
        ['id' => '101', 'votes' => '0', 'title' => '骨仔肉湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/101.webp&nt=1&v=20250620'],
        ['id' => '102', 'votes' => '0', 'title' => '麵茶', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/102.webp&nt=1&v=20250620'],
        ['id' => '103', 'votes' => '0', 'title' => '粿仔湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/103.webp&nt=1&v=20250620'],
        ['id' => '104', 'votes' => '0', 'title' => '麻糬', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/104.webp&nt=1&v=20250620'],
        ['id' => '105', 'votes' => '0', 'title' => '九層粿', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/105.webp&nt=1&v=20250620'],
        ['id' => '106', 'votes' => '0', 'title' => '砂鍋魚頭', 'tags' => '海鮮類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/106.webp&nt=1&v=20250620'],
        ['id' => '107', 'votes' => '0', 'title' => '煎粿', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/107.webp&nt=1&v=20250620'],
        ['id' => '108', 'votes' => '0', 'title' => '肉捲', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/108.webp&nt=1&v=20250620'],
        ['id' => '109', 'votes' => '0', 'title' => '水晶餃', 'tags' => '餃子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/109.webp&nt=1&v=20250620'],
        ['id' => '110', 'votes' => '0', 'title' => '蝦捲', 'tags' => '海鮮類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/110.webp&nt=1&v=20250620'],
        ['id' => '111', 'votes' => '0', 'title' => '虱目魚丸湯', 'tags' => '羹湯類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/111.webp&nt=1&v=20250620'],
        ['id' => '112', 'votes' => '0', 'title' => '黑輪', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/112.webp&nt=1&v=20250620'],
        ['id' => '113', 'votes' => '0', 'title' => '綠豆饌', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/113.webp&nt=1&v=20250620'],
        ['id' => '114', 'votes' => '0', 'title' => '豆簽羹', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/114.webp&nt=1&v=20250620'],
        ['id' => '115', 'votes' => '0', 'title' => '草仔粿', 'tags' => '米食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/115.webp&nt=1&v=20250620'],
        ['id' => '116', 'votes' => '0', 'title' => '豆乳雞', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/116.webp&nt=1&v=20250620'],
        ['id' => '117', 'votes' => '0', 'title' => '八寶冰', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/117.webp&nt=1&v=20250620'],
        ['id' => '118', 'votes' => '0', 'title' => '楊桃冰', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/118.webp&nt=1&v=20250620'],
        ['id' => '119', 'votes' => '0', 'title' => '麻醬麵', 'tags' => '麵食類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/119.webp&nt=1&v=20250620'],
        ['id' => '120', 'votes' => '0', 'title' => '炸餛飩', 'tags' => '餃子類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/120.webp&nt=1&v=20250620'],
        ['id' => '121', 'votes' => '0', 'title' => '車輪餅 / 紅豆餅', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/121.webp&nt=1&v=20250620'],
        ['id' => '122', 'votes' => '0', 'title' => '花生捲冰淇淋', 'tags' => '甜品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/122.webp&nt=1&v=20250620'],
        ['id' => '123', 'votes' => '0', 'title' => '東山鴨頭', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/123.webp&nt=1&v=20250620'],
        ['id' => '124', 'votes' => '0', 'title' => '紅燒肉', 'tags' => '肉品類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/124.webp&nt=1&v=20250620'],
        ['id' => '125', 'votes' => '0', 'title' => '炸豆包', 'tags' => '小菜類', 'image' => 'https://pgw.udn.com.tw/gw/photo.php?u=https://event.udn.com/bd_500bowls_vote2025/image/food/125.webp&nt=1&v=20250620'],
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