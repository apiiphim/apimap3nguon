<?php
header("Content-Type: application/json; charset=UTF-8");

function fetchDataFromApi($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
    ]);

    try {
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode == 200 && !empty($response)) {
            return json_decode($response, true);
        }
    } catch (\Exception $e) {
        return null;
    } finally {
        curl_close($ch);
    }
    
    return null;
}
function convertNguoncPageMap($nguoncItem) {
    return [
        'modified' => [
            'time' => $nguoncItem['modified']['time'] ?? null
        ],
        '_id' => $nguoncItem['_id'] ?? null,
        'name' => $nguoncItem['name'] ?? null,
        'slug' => $nguoncItem['slug'] ?? null,
        'origin_name' => $nguoncItem['origin_name'] ?? null,
        'thumb_url' => $nguoncItem['thumb_url'] ?? null,
        'poster_url' => $nguoncItem['poster_url'] ?? null,
        'year' => $nguoncItem['year'] ?? null
    ];
}

function convertNguoncToOphimMap($nguoncData) {
    $type = "series"; 
	$status = "completed"; 

    foreach($nguoncData['movie']['category'][1]['list'] as $category) {
        $type_category = $category['name'];
        if($type_category == 'Phim bộ'){
                $type = 'series';
        }elseif($type_category == 'Phim lẻ'){
            $type = 'single';
        }elseif($type_category == 'TV shows'){
            $type = 'tvshows';
        }
        if($type_category == 'Phim đang chiếu'){
                $status = 'ongoing'; 
        }
    }

    foreach($nguoncData['movie']['category'][2]['list'] as $layhoathinh) {
        $thongtintype = $layhoathinh['name'];
        if ($thongtintype == "Hoạt Hình"){
            $type = 'hoathinh';
        }
    }



    $ophimData = [
        "status" => true,
        "msg" => "",
        "movie" => [
            "_id" => $nguoncData['movie']['id'],
            "name" => $nguoncData['movie']['name'],
            "slug" => $nguoncData['movie']['slug'],
            "origin_name" => $nguoncData['movie']['original_name'],
            "content" => $nguoncData['movie']['description'],
            "type" => $type,
            "status" => $status,
            "is_copyright" => false,
            "notify" => '',
            "showtimes" => '',
            "thumb_url" => $nguoncData['movie']['thumb_url'],
            "poster_url" => $nguoncData['movie']['poster_url'],
            "time" => $nguoncData['movie']['time'],
            "episode_current" => $nguoncData['movie']['current_episode'],
            "episode_total" => $nguoncData['movie']['total_episodes'],
            "quality" => $nguoncData['movie']['quality'],
            "lang" => $nguoncData['movie']['language'],
            "year" => null,
            "view" => 0, 
            "actor" => $nguoncData['movie']['casts'] ? explode(", ", $nguoncData['movie']['casts']) : [""],
            "director" => $nguoncData['movie']['director'] ? [$nguoncData['movie']['director']] : [""],
            "category" => [],
            "country" => [],
            "chieurap" => false, 
        ],
        "episodes" => []
    ];

    foreach ($nguoncData['movie']['category'] as $categoryGroup) {
        $groupName = strtolower($categoryGroup['group']['name']);
        if ($groupName === "năm") {
            $ophimData['movie']['year'] = $categoryGroup['list'][0]['name'];
        } elseif ($groupName === "quốc gia") {
            foreach ($categoryGroup['list'] as $country) {
                $ophimData['movie']['country'][] = [
                    "id" => uniqid(), 
                    "name" => $country['name'],
                    "slug" => strtolower(str_replace(" ", "-", $country['name']))
                ];
            }
        } else {
            foreach ($categoryGroup['list'] as $category) {
                $ophimData['movie']['category'][] = [
                    "id" => uniqid(), 
                    "name" => $category['name'],
                    "slug" => strtolower(str_replace(" ", "-", $category['name']))
                ];
            }
        }
    }

    foreach ($nguoncData['movie']['episodes'] as $episode) {
        $serverData = [];
        foreach ($episode['items'] as $item) {
            $serverData[] = [
                "name" => $item['name'],
                "slug" => $item['slug'],
                "filename" => "", 
                "link_embed" => $item['embed'],
                "link_m3u8" => ''
            ];
        }
        $ophimData['episodes'][] = [
            "server_name" => $episode['server_name'],
            "server_data" => $serverData
        ];
    }

    return $ophimData;
}


if (isset($_GET['slug'])) {
    $slug = htmlspecialchars($_GET['slug'], ENT_QUOTES, 'UTF-8');
    $nguoncUrl = "https://phim.nguonc.com/api/film/$slug";
    $nguoncData = fetchDataFromApi($nguoncUrl);

    if ($nguoncData && $nguoncData['status'] === "success") {
        $ophimData = convertNguoncToOphimMap($nguoncData);
        echo json_encode($ophimData);
    } else {
        echo json_encode(['status' => false, 'msg' => 'No valid data found']);
    }
} elseif (isset($_GET['page'])) {
    $page = intval($_GET['page']);
    $nguoncUrl = "https://phim.nguonc.com/api/films/phim-moi-cap-nhat?page=$page";
    $nguoncData = fetchDataFromApi($nguoncUrl);

    if ($nguoncData && $nguoncData['status'] === "success") {
        $ophimData = [
            'status' => true,
            'items' => array_map('convertNguoncPageMap', $nguoncData['items']),
            'pagination' => [
                'totalItems' => $nguoncData['paginate']['total_items'],
                'totalItemsPerPage' => $nguoncData['paginate']['items_per_page'],
                'currentPage' => $nguoncData['paginate']['current_page'],
                'totalPages' => $nguoncData['paginate']['total_page']
            ]
        ];
        echo json_encode($ophimData);
    } else {
        echo json_encode(['status' => false, 'msg' => 'No valid data found']);
    }
}
