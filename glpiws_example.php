<?php

/**  
 * GLPI WebService Integration
 * 
 * @author diego.pessanha
 * @since 2014-10-09
 *
 * Utiliza a classe GlpiWebService para integrar com o glpi via soap
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once dirname(__FILE__) . "/glpiws.class.php";

echo "\n*** GLPI SOAP Integration ***\n\n";

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
 
// Ticket information
$ticket = array(
    'content' => "Description",
    'status' => 1, // Progress
    'type' => 1, // Incident
    'urgency' => 2, // Low
    'impact' => 2, // Low
    'category' => 155, // Category 
    'item' => 534, // Item ID
    'itemtype' => 'Computer', // item type
    'title' => "Ticket Title (Example)", 
    'source' => 'WebServices',
    'date' => '2014-11-10 01:12:30'
);
 
$user = 22; // Usuário a quem foi atribuido o chamado
$group = null; // Grupo a quem foi atribuido o chamado
$solutionType = 3;
$closeMessage = $solutionMessage = "Executado sem falhas";

// Create the ticket and set the solution 
if (!$id = $GlpiWebService->createTicket($ticket, $user, $group))
{
    echo $GlpiWebService->getErrors() . "\n\n";
    exit(1);
}

// Upload a file
if (!$GlpiWebService->uploadDocument($id, "nome_documento.jpg", "/home/diego/Pictures/Jedi_Logo.jpg"))
{
    echo $GlpiWebService->getErrors() . "\n\n";
    exit(1);
}

// Set the solution 
if (!$GlpiWebService->resolveTicket($id, $solutionType, $solutionMessage))
{
    echo $GlpiWebService->getErrors() . "\n\n";
    exit(1);
}

// Close the ticket
if (!$GlpiWebService->closeTicket($id, $closeMessage))
{
    echo $GlpiWebService->getErrors() . "\n\n";
    exit(1);
}

echo "Ticket $id created successfuly!\n\n";
exit(0);//Success
