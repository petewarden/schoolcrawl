#!/usr/bin/php
<?php
// 
//
// By Pete Warden <pete@petewarden.com>, freely reusable, see http://petewarden.typepad.com for more

require_once('parallelcurl.php');
require_once('cliargs.php');

define('SCHOOL_RE', '@<a href="([^"]+)"><div class="va-search-item">    <div class="clear" >        <span style="font-size:15px; font-weight: bold" >([^<]+)</span>    </div>    <p style="font-style:italic;">([^<]+)</p></div>@');

// This function gets called back for each request that completes
function on_request_done($content, $url, $ch, $data) {
    
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);    
    if ($httpcode !== 200) {
        print "Fetch error $httpcode for '$url'\n";
        return;
    }
    
    $output_handle = $data['output_handle'];
    $rank = $data['rank'];

    $text = str_replace("\n", "", $content);

    if (!preg_match_all(SCHOOL_RE, $text, $matches, PREG_SET_ORDER))
    {
        error_log("Failed to match RE with '$text'");
        return;
    }

    foreach ($matches as $match)
    {
        $url_suffix = trim($match[1]);
        $name = trim($match[2]);
        $address = trim($match[3]);
        
        $tooltip = $name.' - '.$rank;
        $full_url = 'http://projects.latimes.com'.$url_suffix;
        
        $output = array($address, $tooltip, $full_url, $rank);
        
        fputcsv($output_handle, $output);
    }

}

$cliargs = array(
	'output' => array(
		'short' => 'o',
		'type' => 'optional',
		'description' => 'The file to write the output list of URLs to - if unset will write to stdout',
        'default' => 'php://stdout',
	),
    'maxrequests' => array(
        'short' => 'm',
        'type' => 'optional',
        'description' => 'How many requests to run in parallel',
        'default' => '10',
    ),
    'organization' => array(
        'short' => 'r',
        'type' => 'required',
        'description' => 'The name of the organization or company running this crawler',
    ),
    'email' => array(
        'short' => 'e',
        'type' => 'required',
        'description' => 'An email address where server owners can report any problems with this crawler',
    ),    
);	

ini_set('memory_limit', '-1');

$options = cliargs_get_options($cliargs);

$output = $options['output'];
$max_requests = $options['maxrequests'];
$organization = $options['organization'];
$email = $options['email'];

if (empty($organization) || empty($email) || (!strpos($email, '@')))
    die("You need to specify a valid organization and email address (found '$organization', '$email')\n");

$agent = 'Crawler from '.$organization;
$agent .= ' - contact '.$email;
$agent .= ' to report any problems with my crawling. Based on code from http://petewarden.typepad.com';

$curl_options = array(
    CURLOPT_SSL_VERIFYPEER => FALSE,
    CURLOPT_SSL_VERIFYHOST => FALSE,
	CURLOPT_FOLLOWLOCATION => TRUE,
	CURLOPT_USERAGENT => $agent,
);

$base_url = 'http://projects.latimes.com/value-added/rank/school/';

$ranks = range(1, 5);

$output_handle = fopen($output, 'w');

fputcsv($output_handle, array('address', 'tooltip', 'url', 'value'));

$parallel_curl = new ParallelCurl($max_requests, $curl_options);

foreach ($ranks as $rank) {
        
    $full_url = $base_url.$rank.'/';
    $data = array('output_handle' => $output_handle, 'rank' => $rank);
    $parallel_curl->startRequest($full_url, 'on_request_done', $data);
}

// This should be called when you need to wait for the requests to finish.
// This will automatically run on destruct of the ParallelCurl object, so the next line is optional.
$parallel_curl->finishAllRequests();

?>