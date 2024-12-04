<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type");

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

function mergeMovieData($primaryData, $secondaryData) {
    if (isset($secondaryData['movie']['content']) && strlen($secondaryData['movie']['content']) > strlen($primaryData['movie']['content'])) {
        $primaryData['movie']['content'] = $secondaryData['movie']['content'];
    }

    $fieldsToMerge = ['trailer_url', 'time', 'quality', 'lang', 'episode_current', 'episode_total', 'view', 'actor', 'director'];

    foreach ($fieldsToMerge as $field) {
        if (empty($primaryData['movie'][$field]) && !empty($secondaryData['movie'][$field])) {
            $primaryData['movie'][$field] = $secondaryData['movie'][$field];
        }
    }

    return $primaryData;
}

function cleanServerName($name) {
    return str_replace('Tập', '', $name);
}

function addServerNames($episodes) {
    $serverNamesCount = 1;
    foreach ($episodes as &$episode) {
        $episode['server_name'] = '[SV #' . $serverNamesCount . '] ' . $episode['server_name'];
        $serverNamesCount++;
        foreach ($episode['server_data'] as &$server) {
            $server['name'] = cleanServerName($server['name']);
        }
    }
    return $episodes;
}

function handleMovieData($slug) {
    $ophimUrl = "https://ophim1.com/phim/$slug";
    $phimApiUrl = "https://phimapi.com/phim/$slug";
    $nguoncApiUrl = "/nguonc.php?slug=$slug";
    
    $ophimData = fetchDataFromApi($ophimUrl);
    $phimApiData = fetchDataFromApi($phimApiUrl);
    $nguoncApiData = fetchDataFromApi($nguoncApiUrl);

    if ($ophimData && $ophimData['status']) {
        $primaryData = $ophimData;
        
        $ophimYear = $primaryData['movie']['year'] ?? null;
        $ophimType = $primaryData['movie']['type'] ?? null;
        
        if ($phimApiData && $phimApiData['status']) {
            $phimApiYear = $phimApiData['movie']['year'] ?? null;
            $phimApiType = $phimApiData['movie']['type'] ?? null;
            
            if ($ophimYear == $phimApiYear && $ophimType == $phimApiType) {
                foreach ($phimApiData['episodes'] as $episode) {
                    $primaryData['episodes'][] = $episode;
                }
                $primaryData = mergeMovieData($primaryData, $phimApiData);
            }
        }
        
        if ($nguoncApiData && $nguoncApiData['status']) {
            $nguoncYear = $nguoncApiData['movie']['year'] ?? null;
            $nguoncType = $nguoncApiData['movie']['type'] ?? null;
            
            if ($ophimYear == $nguoncYear && $ophimType == $nguoncType) {
                foreach ($nguoncApiData['episodes'] as $episode) {
                    $primaryData['episodes'][] = $episode;
                }
                $primaryData = mergeMovieData($primaryData, $nguoncApiData);
            }
        }
    } 
    elseif ($phimApiData && $phimApiData['status']) {
        $primaryData = $phimApiData;
        if (isset($primaryData['movie']['poster_url'], $primaryData['movie']['thumb_url'])) {
            $temp = $primaryData['movie']['poster_url'];
            $primaryData['movie']['poster_url'] = $primaryData['movie']['thumb_url'];
            $primaryData['movie']['thumb_url'] = $temp;
        }
        
        if ($nguoncApiData && $nguoncApiData['status']) {
            $primaryYear = $primaryData['movie']['year'] ?? null;
            $nguoncYear = $nguoncApiData['movie']['year'] ?? null;
            $primaryType = $primaryData['movie']['type'] ?? null;
            $nguoncType = $nguoncApiData['movie']['type'] ?? null;
            
            if ($primaryYear == $nguoncYear && $primaryType == $nguoncType) {
                foreach ($nguoncApiData['episodes'] as $episode) {
                    $primaryData['episodes'][] = $episode;
                }
                $primaryData = mergeMovieData($primaryData, $nguoncApiData);
            }
        }
    }
    elseif ($nguoncApiData && $nguoncApiData['status']) {
        $primaryData = $nguoncApiData;
    } else {
        return null;
    }

    if (!empty($primaryData['episodes'])) {
        $primaryData['episodes'] = addServerNames($primaryData['episodes']);
    }

    return $primaryData;
}

if (isset($_GET['slug'])) {
    $slug = htmlspecialchars($_GET['slug'], ENT_QUOTES, 'UTF-8');
    $movieData = handleMovieData($slug);

    if ($movieData) {
        header('Content-Type: application/json');
        echo json_encode($movieData);
    } else {
        echo json_encode(['status' => false, 'msg' => 'Không tìm thấy dữ liệu hợp lệ']);
    }
} else {
    echo json_encode(['status' => false, 'msg' => 'Thiếu tham số slug']);
}
