#!/usr/bin/env php
<?php
// Test if executed from CLI
if (!defined('STDIN')) {
  echo("Error : Should only be executed from CLI.");  
  exit;
}

// Load liraries
$lib = require_once dirname(__FILE__) . "/lib/loader.php";
// Load configuration files
$config = $lib->loadYaml("config.yml");


/**
 * The command "links" outputs the list of all the links
 * 
 */
if ($argc > 1 && $argv[1] == "dump-links") {
    // Download the API reference table of content 
    $html = $lib->fetchHtml($config['api_ref_toc_url']);
    $links = getAllLinks($html);
    foreach ($links as $link) {
        echo $link . "\n";
    }
    
    exit;
}

/**
 * The command "method-data"
 */
if ($argc > 2 && $argv[1] == "dump-method-data" ) {
    $method = $argv[2];
    $url = getRootUrl($config['api_ref_toc_url']) . "user/${method}.html";
    print_r(fetchMethodData($url));

    exit;
}

/**
 * The command "method"
 */
if ($argc > 2 && $argv[1] == "method" ) {
    $method = $argv[2];
    $url = getRootUrl($config['api_ref_toc_url']) . "user/${method}.html";
    $methodData = fetchMethodData($url);
    
    $lib->render("method.php.twig", array(
        "method" => $methodData,
        "config" => $config,
    ));

    exit;
}


// Here : no argument given (or not valid), generating the class
// Download the API reference table of content 
$html = $lib->fetchHtml($config['api_ref_toc_url']);
$rootUrl = getRootUrl($config['api_ref_toc_url']);

$methods = array();
// walk through all links
foreach (getAllLinks($html) as $link) {
    $url =  $rootUrl . $link;
    $methods[] = fetchMethodData($url);
}

$lib->render("class.php.twig", array(
    "methods" => $methods,
    "config" => $config,
));
    
    
/******************
  Functions
 ******************/

/**
 * Get all the hyperlinks from the HTML document given
 * and return them in an array
 */
function getAllLinks($html) {
    $links = array();
    
    foreach($html->find('a') as $a) {
        $url = $a->href;
        
        // Exclude page that are not method documentation
        // You may need to edit the rules if the documentation has changed
        if ($url == "http://cloud.com" || substr($url, 0, 8) == "user/2.2") {
            continue;
        }
        
        $links[] = $url;
    }
    
    return $links;
}

/**
 * Match the root of the table of content
 * for http://download.cloud.com/releases/2.2.0/api_2.2.4/TOC_User.html
 * the root is http://download.cloud.com/releases/2.2.0/api_2.2.4/
 */
function getRootUrl($url) {
    preg_match("/^(.*\/)[^\/]+$/", $url, $matches);
    return $matches[1];
}

/**
 * Fetch the data of the reference page of one method
 * and returns it in an array
 */
function fetchMethodData($url) {
    global $lib;
    $html = $lib->fetchHtml($url);
    // The name of the method is in the first and only one h1
    $title = $html->find('h1', 0);
    $data = array(
        'name' => trim($title->plaintext),
        // The description of the method is in the next block
        'description' => trim($title->next_sibling()->plaintext),
    );
    
    // The arguments of the method are all in the first table
    $params_table = $html->find('table', 0);

    // then, capturing the 3 cells of each lines :
    // parameter name, description of the paramter and wether if it is required or not
    foreach($params_table->find('tr') as $tr) {
        if (trim($tr->find('td', 0)->plaintext) != "Parameter Name") {
            $data['params'][] = array(
                "name" => trim($tr->find('td', 0)->plaintext),
                "description" => trim($tr->find('td', 1)->plaintext),
                "required" => trim($tr->find('td', 2)->plaintext),
            );
        }
    }
    
    // All the methods strating with list have a additionnal parameter
    // for pagination, not required
    if (substr($data['name'], 0, 4) == "list") {
        $data['params']['page'] = array(
            "description" => "Pagination",
            "required" => "false",
        );
    }
    
    return $data;
}