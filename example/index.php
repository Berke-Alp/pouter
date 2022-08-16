<?php
include_once 'pouter/Parameter.php';
include_once 'pouter/Router.php';

// We can define a views folder
// $router = new Router('myviews');
// Default is views
$router = new Router();

// Catch the status code and do something with it
// Note, this will be called only if router doesn't match the request url
$router->onError = function($status_code) {
    // You can provide a view data
    Router::view('error', array(
        'status_code' => $status_code,
        'other_data' => 31
    ));
    exit;
};

// Index route
$router->get('/', function() {
    // We can define view
    // You don't need to specify the views folder
    // Router::view('index') would also work
    // Router::view('index.php') would also work
    Router::view('views/index');
});

$router->get('/admin', function() {
    // You can provide an absolute path for the view
    // // Router::view('/home/someuser/non-php-folder/views/admin/dashboard.php') would also work
    Router::view('views/admin/index');
});

// Order doesn't matter, if request matches with /admin, /admin will work
// else, this route will be called
$router->get('/[rp]', function(RouteParam $rp) {
    echo "I got you, your parameter is $rp";
});

// RouteParam types are not supported (yet)
// This route will catch any id parameter (ex: /user/1, /user/123, /user/GordonRamsay, /user/Berke.Alp)
$router->get('/user/[id]', function(RouteParam $id) {
    echo "Given user id is $id";
});

// 'example.com/search' and 'example.com/search?q=' will not work since get parameter $q is required and must not be empty
// So 'example.com/search?q=How+to+be+rich' will work
$router->get('/search', function(GetParam $q) {
    echo "I needed a query, you gave me this: $q <br>";
    echo "Congrats.";
});


// Don't forget to call this, or the router won't be working ;)
$router->run();