<?php
ini_set("memory_limit", "1024M");
require 'rolling-curl/src/RollingCurl/RollingCurl.php';
require 'rolling-curl/src/RollingCurl/Request.php';
function Timer() {
    list($u, $s) = explode(' ', microtime(false));
    return bcadd($u, $s, 7);
}
$start = Timer();

$rollingCurl = new \RollingCurl\RollingCurl();

for ($i = 1; $i < 258; $i++) {
    $data = array(
        'action' => 'query_plugins',
        'request' => serialize((object)array(
            'page' => $i,
            'per_page' => 100,
            'fields' => array(
                'description'       => false,
                'compatibility'     => false,
                'homepage'          => false,
                'num_ratings'       => false,
                'rating'            => false,
                'requires'          => false,
                'short_description' => false,
                'tested'            => false,
            )
        ))
    );
    $rollingCurl->post('http://api.wordpress.org/plugins/info/1.0/', http_build_query($data));
}
$results = file_exists('wp_plg_db.json')?json_decode(file_get_contents('wp_plg_db.json'),true):array();
echo "Fetching..." . PHP_EOL;
$rollingCurl->setCallback(function (\RollingCurl\Request $request, \RollingCurl\RollingCurl $rollingCurl) use (&$results) {
    if ($request->getResponseInfo()->http_code == 200) {
        $json = unserialize($request->getResponseText());
        if (isset($json->plugins)) {
            echo "Fetch complete for (" . $request->getUrl() . ") Page:" . $json->info['page'] . PHP_EOL;
            
            foreach ($json->plugins as $plugin) {
                if(!array_key_exists($plugin->slug,$results)) {
                    $results[$plugin->slug]=null;
                    echo "Fetch new for (" . $plugin->slug . ") Plugin:" . $plugin->name . PHP_EOL;
                } 
                $data = array(
                    'action' => 'plugin_information',
                    'request' => serialize((object)array(
                        'slug' => $plugin->slug,
                        'fields' => array(
                            'requires'      => false,
                            'tested'        => false,
                            'compatibility' => false,
                            'rating'        => false,
                            'num_ratings'   => false,
                            'downloaded'    => false,
                            'last_updated'  => false,
                            'added'         => false,
                            'homepage'      => false,
                            'sections'      => false,
                            'tags'          => false,
                        )
                    ))
                );
                $rollingCurl->post('http://api.wordpress.org/plugins/info/1.0/', http_build_query($data));
            }
        }
        if (isset($json->download_link)) {
           # echo $json->slug . " ||| " . $json->download_link . "\n";
            $results[$json->slug]=array(
                'name' => $json->name,
//              'slug' => $json->slug,
                'download_link' => $json->download_link
            );
            file_put_contents('wp_plg_db.json', json_encode($results), LOCK_EX );           
        }
    } else {
        $rollingCurl->post($request->getUrl(), $request->getPostData());
        
        echo "Fetch error for (" . $request->getUrl() . ")" . PHP_EOL;
    }
})->setSimultaneousLimit(25)->execute();;

echo "...done in: ".bcsub(Timer() , $start, 4)." sec".PHP_EOL;

echo "All results: " . PHP_EOL;

file_put_contents('wp_plg_db.json', json_encode($results));

//print_r($results);
