<?php

// Before we start, let's figure out if the api is being called
// If it is, we need to skip the rendering of the site layout
// To do this, we will check if the request is /api/ and if it is, we will skip the rendering
// This will allow us to return raw data from the API without the site layout

$Route = $_SERVER['REQUEST_URI'];
$ApiRoute = '/api';
$API = false; // Default to false - Renderer will be used
if (strpos($Route, $ApiRoute) !== false) {
    // The API is being called, skip the rendering
    $API = true;
}

require_once './system/bootstrap.php';
require_once './system/Router.php';

// Initialize the router
$Router = new Router();
$Router->getRoute($_SERVER['REQUEST_URI']);
# If the route + Method being called requires the renderer disabled, we will disable it here
// Hack to disable rendering for specific routes for now
// This will be replaced with a more robust solution in the future. Ideally something like Registry::set('Renderer', false); in the controller / method
$DisabledRenderRoutes = array();
$DisabledRenderRoutes[] = 'file/download';
$DisabledRenderRoutes[] = 'image/raw';
$DisabledRenderRoutes[] = 'file/view';

// Log the route being accessed
logger('Route: ' . $Router->Controller . '/' . $Router->Method) ;

// Check if the route is allowed
if ($Router->isAllowedRoute()) {
    // Sanitize the controller and method
    $ControllerName = "Controller" . ucfirst(strtolower($Router->Controller)); // Example: "User" -> "ControllerUser"
    $ControllerFileName = ucfirst(strtolower($Router->Controller)); // Example: "user" -> "User"
    $Method = strtolower($Router->Method);

    // Autoload the controller file if it exists
    $controllerPath = ROOT . '/Controllers/' . $ControllerFileName . '.php';


    if (file_exists($controllerPath)) {
        require_once $controllerPath;
        if (class_exists($ControllerName)) {
            // Disable the renderer for specific routes
            if (in_array(strtolower($Router->Controller . '/' . $Router->Method), $DisabledRenderRoutes)) {
                logger("Disabling renderer for route: " . $Router->Controller . '/' . $Router->Method);
                $API = true; // Disable the render object for API controllers (true) and disabled renderer routes
            }
            
            $Controller = new $ControllerName($API);

            // Check if the method exists in the controller
            if (method_exists($Controller, $Method)) {
                $Controller->$Method(); // Call the method dynamically
            } else {
                // Handle the case where the method does not exist
                logger("Method $Method not found in controller $ControllerName");
                show404($API);
            }
        } else {
            // Handle the case where the controller class does not exist
            logger("Controller class $ControllerName not found.");
            show404($API);
        }
    } else {
        // Handle the case where the controller file does not exist
        logger("Controller file $controllerPath not found.");
        show404($API);
    }
} else {
    // Route is not allowed, load 404 page
    logger("Route: " . $_SERVER['REQUEST_URI'] . " is not allowed.");
    show404($API);
}

// Function to handle showing the 404 page
function show404($API) {
    if($API == true){
        // If the API is being called, return a 404 status code
        header("HTTP/1.0 404 Not Found");
        exit();
    }
    require_once ROOT . '/Controllers/404.php';
    $Controller = new Controller404();
    $Controller->Index(); // Call the default index method for 404
    exit();
}
