<?php
// 引入 CORS 標頭設置
require_once 'cors.php';
require_once 'timezone.php';
require_once 'redis.php';

// 設置返回 JSON 內容類型
header('Content-Type: application/json');

function createDemoBooks($redis)
{
    $books = [
        [
            'id' => '1',
            'votes' => '0',
            'title' => '豐收祭',
            'tags' => '愛情,BL',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/1.webp',
            'author' => '艾米莉',
            'link' => 'https://reading.udn.com/story/products/251524/',
        ],
        [
            'id' => '2',
            'votes' => '0',
            'title' => '生之塔',
            'tags' => '恐怖,科技',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/2.webp',
            'author' => '浮火',
            'link' => 'https://reading.udn.com/story/products/251629/',
        ],
        [
            'id' => '3',
            'votes' => '0',
            'title' => '誰在雨夜低聲說謊',
            'tags' => '犯罪,復仇',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/3.webp',
            'author' => '溫暮',
            'link' => 'https://reading.udn.com/story/products/249270/',
        ],
        [
            'id' => '4',
            'votes' => '0',
            'title' => '疾風夏曲傳',
            'tags' => '愛情,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/4.webp',
            'author' => '藍色月亮',
            'link' => 'https://reading.udn.com/story/products/251725/',
        ],
        [
            'id' => '5',
            'votes' => '0',
            'title' => '我們在彼此的追殺名單上',
            'tags' => '愛情,BL',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/5.webp',
            'author' => '邊境海',
            'link' => 'https://reading.udn.com/story/products/251240/',
        ],
        [
            'id' => '6',
            'votes' => '0',
            'title' => '回春樹／術',
            'tags' => '愛情,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/6.webp',
            'author' => '安卓',
            'link' => 'https://reading.udn.com/story/products/251717/',
        ],
        [
            'id' => '7',
            'votes' => '0',
            'title' => '回到校園當魯蛇',
            'tags' => '寫實,喜劇',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/7.webp',
            'author' => 'Fan',
            'link' => 'https://reading.udn.com/story/products/250427/',
        ],
        [
            'id' => '8',
            'votes' => '0',
            'title' => '魔女今天也在努力工作',
            'tags' => '恐怖,喜劇',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/8.webp',
            'author' => '竹鼠今天也在努力摸魚',
            'link' => 'https://reading.udn.com/story/products/251736/',
        ],
        [
            'id' => '9',
            'votes' => '0',
            'title' => '斷掌山',
            'tags' => '犯罪,社會議題',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/9.webp',
            'author' => 'Shika 西卡',
            'link' => 'https://reading.udn.com/story/products/251734/',
        ],
        [
            'id' => '10',
            'votes' => '0',
            'title' => '玉樞天下（無岸海外傳）',
            'tags' => '愛情,大女主',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/10.webp',
            'author' => '麟',
            'link' => 'https://reading.udn.com/story/products/219291/',
        ],
        [
            'id' => '11',
            'votes' => '0',
            'title' => '女將，陣勢',
            'tags' => '寫實,大女主',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/11.webp',
            'author' => '宴平樂',
            'link' => 'https://reading.udn.com/story/products/251296/',
        ],
        [
            'id' => '12',
            'votes' => '0',
            'title' => '《狐夢奇譚》—不能出門的日子，我家住了一隻狐',
            'tags' => '愛情,GL',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/12.webp',
            'author' => '羽鶴',
            'link' => 'https://reading.udn.com/story/products/251630/',
        ],
        [
            'id' => '13',
            'votes' => '0',
            'title' => '妄月',
            'tags' => '愛情,療癒',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/13.webp',
            'author' => '刁卿蕙',
            'link' => 'https://reading.udn.com/story/products/251470/',
        ],
        [
            'id' => '14',
            'votes' => '0',
            'title' => '辯護士小姐',
            'tags' => '犯罪,歷史',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/14.webp',
            'author' => '官雨青',
            'link' => 'https://reading.udn.com/story/products/251370/',
        ],
        [
            'id' => '15',
            'votes' => '0',
            'title' => '雙生，食夢',
            'tags' => '愛情,大女主',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/15.webp',
            'author' => '薇亦柔止',
            'link' => 'https://reading.udn.com/story/products/251001/',
        ],
        [
            'id' => '16',
            'votes' => '0',
            'title' => '藍色魅影',
            'tags' => '犯罪,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/16.webp',
            'author' => '零零人',
            'link' => 'https://reading.udn.com/story/products/251484/',
        ],
        [
            'id' => '17',
            'votes' => '0',
            'title' => '龍的傳人',
            'tags' => '武俠,民俗信仰',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/17.webp',
            'author' => '白河光',
            'link' => 'https://reading.udn.com/story/products/251406/',
        ],
        [
            'id' => '18',
            'votes' => '0',
            'title' => '緋川規則怪談',
            'tags' => '恐怖,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/18.webp',
            'author' => '陳默',
            'link' => 'https://reading.udn.com/story/products/251375/',
        ],
        [
            'id' => '19',
            'votes' => '0',
            'title' => '愛‧有機可尋',
            'tags' => '愛情,大女主',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/19.webp',
            'author' => '無患子',
            'link' => 'https://reading.udn.com/story/products/251626/',
        ],
        [
            'id' => '20',
            'votes' => '0',
            'title' => '斬手',
            'tags' => '犯罪,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/20.webp',
            'author' => '銜陽',
            'link' => 'https://reading.udn.com/story/products/251645/',
        ],
        [
            'id' => '21',
            'votes' => '0',
            'title' => '彼岸盡頭的那顆草',
            'tags' => '異想,BL',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/21.webp',
            'author' => '宅米蟲子',
            'link' => 'https://reading.udn.com/story/products/251701/',
        ],
        [
            'id' => '22',
            'votes' => '0',
            'title' => '神明救援中',
            'tags' => '異想,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/22.webp',
            'author' => '瀅洝',
            'link' => 'https://reading.udn.com/story/products/250837/',
        ],
        [
            'id' => '23',
            'votes' => '0',
            'title' => '鐘聲',
            'tags' => '恐怖,靈異',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/23.webp',
            'author' => '黃俊衛',
            'link' => 'https://reading.udn.com/story/products/251110/',
        ],
        [
            'id' => '24',
            'votes' => '0',
            'title' => '藍與白',
            'tags' => '寫實,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/24.webp',
            'author' => '被島嶼遺棄的怪人',
            'link' => 'https://reading.udn.com/story/products/250891/',
        ],
        [
            'id' => '25',
            'votes' => '0',
            'title' => '營火旁説的恐怖故事',
            'tags' => '恐怖,復仇',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/25.webp',
            'author' => '李不',
            'link' => 'https://reading.udn.com/story/products/251247/',
        ],
        [
            'id' => '26',
            'votes' => '0',
            'title' => '親愛的瑟爾芬陛下',
            'tags' => '異想,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/26.webp',
            'author' => '琉',
            'link' => 'https://reading.udn.com/story/products/251054/',
        ],
        [
            'id' => '27',
            'votes' => '0',
            'title' => '媒有理想情人',
            'tags' => '寫實,療癒',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/27.webp',
            'author' => '姑婆御',
            'link' => 'https://reading.udn.com/story/products/251126/',
        ],
        [
            'id' => '28',
            'votes' => '0',
            'title' => '探索的世界，構築了碎片的光',
            'tags' => '寫實,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/28.webp',
            'author' => '若薇雅',
            'link' => 'https://reading.udn.com/story/products/251432/',
        ],
        [
            'id' => '29',
            'votes' => '0',
            'title' => '荒境',
            'tags' => '異想,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/29.webp',
            'author' => '文生',
            'link' => 'https://reading.udn.com/story/products/251743/',
        ],
        [
            'id' => '30',
            'votes' => '0',
            'title' => '浪下餘跡．卷一：頑棋',
            'tags' => '武俠,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/30.jpg',
            'author' => '晚笙少主',
            'link' => 'https://reading.udn.com/story/products/251723/',
        ],
        [
            'id' => '31',
            'votes' => '0',
            'title' => '星期二三四',
            'tags' => '寫實,BG',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/31.webp',
            'author' => '高偉欽',
            'link' => 'https://reading.udn.com/story/products/251634/',
        ],
        [
            'id' => '32',
            'votes' => '0',
            'title' => '東寧烏鬼',
            'tags' => '武俠,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/32.webp',
            'author' => '宴平樂',
            'link' => 'https://reading.udn.com/story/products/250468/',
        ],
        [
            'id' => '33',
            'votes' => '0',
            'title' => '那年我們還年輕時',
            'tags' => '異想,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/33.webp',
            'author' => '希姆',
            'link' => 'https://reading.udn.com/story/products/251455/',
        ],
        [
            'id' => '34',
            'votes' => '0',
            'title' => '藥命危機',
            'tags' => '犯罪,社會議題',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/34.webp',
            'author' => '那緹',
            'link' => 'https://reading.udn.com/story/products/251005/',
        ],
        [
            'id' => '35',
            'votes' => '0',
            'title' => '檸檬味的雨天',
            'tags' => '寫實,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/35.webp',
            'author' => '西希',
            'link' => 'https://reading.udn.com/story/products/251698/',
        ],
        [
            'id' => '36',
            'votes' => '0',
            'title' => '遙見清風邀明月',
            'tags' => '寫實,療癒',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/36.webp',
            'author' => '挽月',
            'link' => 'https://reading.udn.com/story/products/251506/',
        ],
        [
            'id' => '37',
            'votes' => '0',
            'title' => '棄子',
            'tags' => '異想,超能力',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/37.webp',
            'author' => '平洋',
            'link' => 'https://reading.udn.com/story/products/251365/',
        ],
        [
            'id' => '38',
            'votes' => '0',
            'title' => '景山群鬼眾',
            'tags' => '武俠,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/38.webp',
            'author' => '水陽',
            'link' => 'https://reading.udn.com/story/products/249601/',
        ],
        [
            'id' => '39',
            'votes' => '0',
            'title' => '看過忘不了',
            'tags' => '犯罪,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/39.webp',
            'author' => '今安',
            'link' => 'https://reading.udn.com/story/products/250440/',
        ],
        [
            'id' => '40',
            'votes' => '0',
            'title' => '那時候，我們就已經死了',
            'tags' => '犯罪,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/40.webp',
            'author' => '阿拐',
            'link' => 'https://reading.udn.com/story/products/251543/',
        ],
        [
            'id' => '41',
            'votes' => '0',
            'title' => '你將不再孤單',
            'tags' => '寫實,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/41.webp',
            'author' => '席綸',
            'link' => 'https://reading.udn.com/story/products/251648/',
        ],
        [
            'id' => '42',
            'votes' => '0',
            'title' => '沉睡的法',
            'tags' => '寫實,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/42.webp',
            'author' => '若薇雅',
            'link' => 'https://reading.udn.com/story/products/251431/',
        ],
        [
            'id' => '43',
            'votes' => '0',
            'title' => '為了妳，重拍一次。',
            'tags' => '異想,超能力',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/43.webp',
            'author' => '希羅',
            'link' => 'https://reading.udn.com/story/products/251097/',
        ],
        [
            'id' => '44',
            'votes' => '0',
            'title' => '青田合作社',
            'tags' => '寫實,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/44.webp',
            'author' => '冬雪十二',
            'link' => 'https://reading.udn.com/story/products/251570/',
        ],
        [
            'id' => '45',
            'votes' => '0',
            'title' => '夜半琴聲',
            'tags' => '犯罪,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/45.webp',
            'author' => '白河光',
            'link' => 'https://reading.udn.com/story/products/250660/',
        ],
        [
            'id' => '46',
            'votes' => '0',
            'title' => '她的最後運算',
            'tags' => '異想,大女主',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/46.webp',
            'author' => '瑞璟',
            'link' => 'https://reading.udn.com/story/products/251446/',
        ],
        [
            'id' => '47',
            'votes' => '0',
            'title' => '家長里短',
            'tags' => '異想,BL',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/47.webp',
            'author' => '林燃',
            'link' => 'https://reading.udn.com/story/products/250356/',
        ],
        [
            'id' => '48',
            'votes' => '0',
            'title' => '伊甸園的來客',
            'tags' => '愛情,救贖',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/48.webp',
            'author' => '夏璚',
            'link' => 'https://reading.udn.com/story/products/251103/',
        ],
        [
            'id' => '49',
            'votes' => '0',
            'title' => '疑案謎情',
            'tags' => '武俠,成長',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/49.webp',
            'author' => '無聞',
            'link' => 'https://reading.udn.com/story/products/250439/',
        ],
        [
            'id' => '50',
            'votes' => '0',
            'title' => '半腦人與他的罪',
            'tags' => '犯罪,科技',
            'image' => 'https://reading.udn.com/story/act/bd_2024storyawards/image/book/50.webp',
            'author' => 'FoolER',
            'link' => 'https://reading.udn.com/story/products/250651/',
        ],
    ];

    $count = 0;
    $result = [];

    try {
        // 清空現有的書籍索引和評分排序
        $redis->del('books:index', 'books:votes');

        // 批次新增書籍資料
        foreach ($books as $book) {
            $bookId = $book['id'];
            $key = "book:{$bookId}";

            // 使用 HMSET 設置書籍資料
            $redis->hmset($key, $book);

            // 添加到書籍索引
            $redis->sadd('books:index', $bookId);

            // 添加到票數排序集
            $redis->zadd('books:votes', intval($book['votes']), $bookId);

            $count++;
        }

        $result = [
            'success' => true,
            'message' => "成功創建 {$count} 本示例書籍",
            'count' => $count
        ];
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'message' => '創建示例書籍失敗: ' . $e->getMessage(),
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
$result = createDemoBooks($redis);

// 輸出結果
echo json_encode($result);
