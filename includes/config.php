<?php
if (!defined('RAWG_API_KEY')) {
    define('RAWG_API_KEY',       getenv('RAWG_API_KEY')       ?: '58aed2d9aedd4274ab81d91356e775f2');
}
if (!defined('IGDB_CLIENT_ID')) {
    define('IGDB_CLIENT_ID',     getenv('IGDB_CLIENT_ID')     ?: 'avrcrn7yp1lyhkkve1et2ha4rwvhzo');
}
if (!defined('IGDB_CLIENT_SECRET')) {
    define('IGDB_CLIENT_SECRET', getenv('IGDB_CLIENT_SECRET') ?: '4rsurue3p8kv0l0kua3orx9y6oxjwf');
}
if (!defined('STEAM_API_KEY')) {
    define('STEAM_API_KEY',      getenv('STEAM_API_KEY')      ?: '4AC57954A37BD60630F8B7CD313B2338');
}
if (!defined('GIANTBOMB_API_KEY')) {
    define('GIANTBOMB_API_KEY',  getenv('GIANTBOMB_API_KEY')  ?: '47c92ad074ff53abc54209d8d75d37046496bcd8');
}
