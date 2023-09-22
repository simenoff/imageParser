<?php
error_reporting(E_ALL);
set_time_limit(60);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Expires: ' . gmdate("D, d M Y H:i:s") . ' GMT');
header('Referrer-Policy: no-referrer');

require_once('simple_html_dom.php');

$url = '';
$result = '';

if (!empty($_GET['url'])) {

    $url = trim($_GET['url']);

    $result = parser($url);
}

echo '
<!DOCTYPE html>
<html lang="ru">
<head>
    <title>Image Parser</title>
    <style>
body{font:18px verdana,arial;text-align:center}
#center{display:inline-block}
input{font:18px verdana,arial}
#url{width:400px;font-weight:bold;color:#777}
#submit{cursor:pointer}
table{border-collapse: collapse}
td{padding:15px;border:1px #eee solid}
img{max-width:350px}
a{color:#09d}a:active{color:#f00}a:hover{color:#f90}
    </style>
</head>
<body>
<div id="center">
    <div style="display:table">
        <h2>Image Parser</h2>
        <form>
            <input type="url" name="url" id="url" value="' . $url . '" required autofocus>&nbsp;<input id="submit" type="submit" value="Go">
        </form>
        <br>' . $result . '
        <br><br>
        <a href="https://github.com/simenoff/imageParser" target="_blank">https://github.com/simenoff/imageParser</a>
        <br><br>
        <a href="https://hh.ru/resume/3e5f8068ff0ba7e4bf0039ed1f495877344764" target="_blank">https://hh.ru/resume/3e5f8068ff0ba7e4bf0039ed1f495877344764</a>
    </div>
</div>
</body>
</html>';

//

function parser(string $url): string {

    if (filter_var($url, FILTER_VALIDATE_URL) === false)
        return 'Неверный URL';

    list($page, $url) = bot($url);

    if (empty($page))
        return 'URL недоступен';

    $page = preg_replace('/<script[^>]*>[^<]*<\/script>/Usi', '', $page); // Удаляем коды счётчиков
    $page = preg_replace('/<noscript[^>]*>[^<]*<\/noscript>/Usi', '', $page); // 

    $html_dom = new simple_html_dom();
    $html_dom->load($page);

    $images = $html_dom->find('img');

    $images_url = array();
    foreach ($images as $img)
        if ($img->src != '' && stripos($img->src, 'data:image') === false) // Пропускаем пустые URL и внедрённые изображения
            $images_url[] = $img->src;

    $html_dom->clear();


    if (count($images_url) == 0)
        return 'Изображения на странице не найдены';


    // Приводим URLs изображений к абсолютному виду
    $base_url = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);

    $full_url = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
    if (empty(parse_url($url, PHP_URL_PATH)))
        $full_url .= '/';
    else
        $full_url .= parse_url($url, PHP_URL_PATH);

    foreach ($images_url as &$iurl) {

        if (preg_match('/^\/\//', $iurl))
            $iurl = 'http:' . $iurl;

        if (!preg_match('/^https?:\/\//i', $iurl)) {

            if (preg_match('/^\//', $iurl))
                $iurl = $base_url . $iurl;
            else
                $iurl = $full_url . $iurl;

        }
        unset($iurl);

    }
    //


    $images_url = array_unique($images_url);
    $images_url = array_values($images_url);

    $count = count($images_url);
    $total_size = 0;


    // Рисуем таблицу
    $result = '<table id="result">';
    for ($i = 0; $i < $count; $i += 4) {

        $result .= '<tr>';
        for ($j = 0; $j < 4; ++$j) {

            if (isset($images_url[$i + $j])) {

                $url = $images_url[$i + $j];

                $img = '<a href="' . $url . '" target="_blank"><img src="' . $url . '" alt=""></a>';

                list($page,) = bot($url);
                $total_size += strlen($page);

            } else {
                $img = '';
            }

            $result .= '<td>' . $img . '</td>';
        }
        $result .= '</tr>';

    }
    $result .= '</table>';
    //


    $total_size /= 1048576;
    $total_size = number_format($total_size, 2, ',', '');

    $result .= '<h3>На странице обнаружено ' . $count . ' изображений на ' . $total_size . ' МБ</h3>';

    return $result;

}

//

function bot(string $url): array {

    $headers[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/117.0';
    $headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8';
    $headers[] = 'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3';

    $c = curl_init();
    curl_setopt($c, CURLOPT_URL, $url);

    curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($c, CURLOPT_ENCODING, 'gzip, deflate');

    curl_setopt($c, CURLOPT_DNS_SHUFFLE_ADDRESSES, true);

    curl_setopt($c, CURLOPT_DNS_CACHE_TIMEOUT, 86400);

    curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($c, CURLOPT_MAXREDIRS, 3);

    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($c, CURLOPT_TIMEOUT, 15);

    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_HEADER, false);
    curl_setopt($c, CURLOPT_NOBODY, false);

    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);

    curl_setopt($c, CURLOPT_COOKIEFILE, '');

    $page = curl_exec($c);

    $endurl = curl_getinfo($c, CURLINFO_EFFECTIVE_URL);

    curl_close($c);

    return array($page, $endurl);
}
