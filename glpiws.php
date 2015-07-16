<?php

/**  
 * GLPI WebService Integration
 * 
 * @since 2014-10-09
 * @param string $servername Name of the server
 * @param string $username [optional]
 *
 */

include_once dirname(__FILE__) . "/glpiws.class.php";

######################
##### Init Vars ######
######################
$group = null; 

$users = array(
    'user_1' => 22,
    'user_2' => 21,
    'user_3' => 16
);

$servers = array(
    'srv01' => 534,
    'srv02' => 535,
    'srv03' => 536
);

######################
#### Handle params ###
######################
 
echo "\n*** GLPI SOAP Integration ***\n\n";

$shortopts = "s:u:d:m:t:";
$options = getopt($shortopts);

if (empty($options) || !isset($options['s']))
{
    echo "[HELP]\n\n";
    echo "-> Utilization: glpiws -s servername [-u username (optional)] [-d date (optional)] [-m message (optional)] [-t dry-run (optional)]\n";
    echo "-> Accepted date format: YYYY-MM-DD HH:MM:SS\n";
    echo "\n\n";  
    exit(1);
}

$server = $options["s"];
$serverName = $server;
$user = (isset($options["u"])) ? $options["u"] : null;
$date = (isset($options["d"])) ? $options["d"] : null;
$message = (isset($options["m"]) && $options["m"] != "-t") ? $options["m"] : null;

$dryRun = false;
if (in_array("-t", $argv)) $dryRun = true;

if (isset($servers[$server]))
{
    $server = $servers[$server];
}
else
{
    echo "ERROR: Invalid server name: $server.\n\n";
    exit(1);
}

if ($user != null && isset($users[$user]))
{
    $user = $users[$user];
}
elseif ($user != null)
{
    echo "ERROR: Invalid username: $user.\n\n";
    exit(1);
}

$regex = '/^(19|20)\d\d[\-\/.](0[1-9]|1[012])[\-\/.](0[1-9]|[12][0-9]|3[01]) ([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/';

if ($date != null && !preg_match($regex, $date))
{
    echo "ERROR: Invalid date format: $date.\n";
    echo "Use this date format: YYYY-MM-DD HH:MM:SS\n\n";
    exit(1);
}

######################
#  GLPI Integration  #
######################

// Init GLPI integration class
$GlpiWebService = new GlpiWebService();
$GlpiWebService->url = "http://www.yourglpi.com/plugins/webservices/soap.php";
$GlpiWebService->ws_user = 'web_srv_usr'; // OPTIONAL Web service user
$GlpiWebService->ws_pass = ''; //OPTIONAL
$GlpiWebService->glpi_user = 'glpi_user'; // GLPI user
$GlpiWebService->glpi_pass = 'glpi_user_pwd';

// Connect and automatically login
if (!$GlpiWebService->connect())
{
    echo $GlpiWebService->getErrors() . "\n\n";
    exit(1);
}
 
$solutionType = 3; //ID of your solution type
$closeMessage = $solutionMessage = "Resolved! Cowabanga!";
$requester = null;

$type = 1;
$categoryId = 155;
$desc = "Lorem Ipsum.";
$title = "Sample Ticket";
 
// Ticket information
$ticket = array(
    'content' => $desc,//"Restart do JBoss - $serverName",
    'status' => 1, // Em Progresso
    'type' => $type, // Incidente
    'urgency' => 2, // Baixa
    'impact' => 2, // Baixo
    'category' => $categoryId, 
    'item' => $server, 
    'itemtype' => 'Computer',
    'title' => $title,
    'source' => 'WebServices'
);

if ($date != null)
{
    $ticket['date'] = $date;
}

if ($requester != null)
{
    $ticket['requester'] = $requester;
}

if (!$dryRun)
{
    // Create the ticket and set the solution 
    if (!$id = $GlpiWebService->createTicket($ticket, $user, null, $solutionType, $solutionMessage, $closeMessage))
    {
        echo $GlpiWebService->getErrors() . "\n\n";
        exit(1);
    }
    
    echo "Ticket $id created successfuly!\n\n";
}
else
{
    echo "\n";
    echo "##################\n";
    echo "# Ticket Preview #\n";
    echo "##################\n\n";
    print_r($ticket);
    echo "\n---> Texto da Solução: \"$solutionMessage\"";
    echo "\n---> Texto de Fechamento: \"$closeMessage\"";
    echo "\n";
}

exit(0);//Success
