<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$cache_file = __DIR__ . '/cctv_news_cache.json';
$cache_time = 600;

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
    readfile($cache_file);
    exit;
}

$news = [];
$ctx = stream_context_create([
    'http' => ['timeout' => 8, 'header' => "User-Agent: Mozilla/5.0\r\n"],
    'ssl' => ['verify_peer' => false]
]);

$jsonp = @file_get_contents('https://news.cctv.com/2019/07/gaiban/cmsdatainterface/page/news_1.jsonp', false, $ctx);

if ($jsonp && preg_match('/^news\((.*)\)$/s', trim($jsonp), $m)) {
    $data = json_decode($m[1], true);
    if (!empty($data['data']['list'])) {
        foreach ($data['data']['list'] as $item) {
            $title = $item['title'] ?? '';
            $url = $item['url'] ?? '#';
            $image = $item['image'] ?? '';
            $brief = $item['brief'] ?? '';
            if (mb_strlen($title) > 6) {
                $news[] = [
                    'title' => $title,
                    'url' => $url,
                    'image' => $image,
                    'brief' => $brief,
                ];
            }
        }
    }
}

if (empty($news)) {
    $news = [
        ['title' => '习近平主持召开中央全面深化改革委员会会议', 'url' => '#', 'image' => '', 'brief' => ''],
        ['title' => '上半年国内生产总值同比增长5.2%', 'url' => '#', 'image' => '', 'brief' => ''],
        ['title' => '我国载人航天工程取得新突破', 'url' => '#', 'image' => '', 'brief' => ''],
        ['title' => '全国安全生产形势持续稳定向好', 'url' => '#', 'image' => '', 'brief' => ''],
        ['title' => '各地积极应对异常天气 保障群众生产生活', 'url' => '#', 'image' => '', 'brief' => ''],
        ['title' => '数字经济成为推动高质量发展重要引擎', 'url' => '#', 'image' => '', 'brief' => ''],
    ];
}

$result = json_encode(array_slice($news, 0, 15), JSON_UNESCAPED_UNICODE);
file_put_contents($cache_file, $result);
echo $result;
