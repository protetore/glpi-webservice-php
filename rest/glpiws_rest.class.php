<?php
/**
 * GlpiWebService
 *
 * A class to handle basic integration with GLPI WebServices plugin.
 *
 * @author diego.pessanha
 * @since 2015-01-15
 *
 * @var string $url
 * @var string $ws_user
 * @var string $ws_pass
 * @var string $glpi_user
 * @var string $glpi_pass
 * @var string $error
 * @var string $session
 */
class GlpiWebService
{
    public $url = "http://YOUR-URL/plugins/webservices/rest.php";
    public $ws_user = '';
    public $ws_pass = '';    
    public $glpi_user = '';
    public $glpi_pass = '';
    public $session = null;
    
    protected $errors = array();
    
    /**
     * getErrors
     *
     * Treat the erros array and return a formatted string
     *
     * @author diego.pessanha
     * @since 2014-10-09
     * @return string A string with all errors or null if empty
     */
    public function getErrors()
    {
        if (!empty($this->errors))
            return "ERROR: " . implode("\n[ERROR]", $this->errors);
        else
            return null;
    }
    
    private function doRequest($method,$args)
    {
        $request = "";
        foreach ($args as $key => $value) 
        {
           $request.= "&$key=$value";
        }
        
        if (!empty($this->session))
        {
            $request.="&session=".$this->session;
        }
        
        echo "+ Calling '$method' on {$this->url}?method=".$method."$request\n\n";

        $file = file_get_contents($this->url."?method=".$method."$request", false);

        if (!$file) 
        {
           $this->errors[] = 'Could not connect. No response.';
           return false;
        }

        $response = json_decode($file, true);
        if (!is_array($response)) 
        {
           $this->errors[] = "Could not connect. Bad response: $file";
           return false;
        }

        if (isset($response['faultCode'])) 
        {
            $this->errors[] = "REST error(".$response['faultCode']."): ".$response['faultString'];
            return false;
        } 
        else 
        {
           return $response;
        }
    }
    
    /**
     * login
     *
     * Authentication method
     *
     * @author diego.pessanha
     * @since 2014-10-09
     * @return boolean
     */
    public function login()
    {
        // Authenticate
        $params = array(
            'login_name' => $this->glpi_user,
            'login_password' => $this->glpi_pass
        );

        $response = $this->doRequest('glpi.doLogin',$params);
        $this->session = $response['session'];   
        
        echo "+ Connected with session: {$this->session}\n\n";
        print_r($response);
        echo "\n\n";        
        
        return true;
    }

    /**
     * createTicket
     *
     * Create an openned ticket (cannot create an already closed or resolved ticket)
     * If a solution is set, it will call a method to set a solution to the ticket
     * If a user or group is set, it will call a method to assign the ticket to this entity
     *
     * @author diego.pessanha
     * @since 2014-10-09
     *
     * @param array $params params of the ticket tah will be sent to GLPI 
     * @param integer $solutionType ID of the solution in GLPI
     * @param string $solutionDesc
     * @param integer $userId ID of the user in GLPI
     * @param integer $groupId ID of the group in GLPI
     *
     * @return integer|boolean The ID of the ticket or FALSE in case of error
     *
     */
    public function createTicket($params, $userId = null, $groupId = null, $solutionType = null, $solutionDesc = null, $closeMessage = null)
    {
        if ($params == null)
        {
            $this->errors[] = 'Params are required!';
            return false;
        }
        
        $response = $this->doRequest('glpi.createTicket',$params);
                
        if (empty($response) || !isset($response['id']))
        {
            $this->errors[] = 'No response from GLPI WebService.';
            return false;
        }
            
        $id = $response['id'];
            
        if ($userId || $groupId)
        {
            if (!$this->assignTicket($id, $userId, $groupId))
            {
                return false;
            }
        }
            
        if ($solutionType)
        {
            if (!$this->resolveTicket($id, $solutionType, $solutionDesc))
            {
                return false;
            }
        }
        
        if ($closeMessage)
        {
            if (!$this->closeTicket($id, $closeMessage))
            {
                return false;
            }
        }
        
        // Return the ticket ID
        return $id;
    }

    /**
     * resolveTicket
     *
     * Set the status of an existing ticket to "Resolved"
     *
     * @author diego.pessanha
     * @since 2014-10-09
     *
     * @param integer $id ID of the previously created tcket GLPI
     * @param integer $solutionType ID of the solution in GLPI
     * @param string $solutionDesc
     * @return boolean
     *
     */
    public function resolveTicket($id, $solutionType, $solutionDesc)
    {
        if ($id == null || $solutionType == null || $solutionDesc == null)
        {
            $this->errors[] = "ID, solution type and message are required! Ticket $id was created but could not be resolved.";
            return false;
        }       
        
        $params['ticket'] = $id;
        $params['type'] = $solutionType;
        $params['solution'] = $solutionDesc;
        
        $response = $this->doRequest('glpi.setTicketSolution',$params);
        
        // Return webservice response object
        return true;
    }

    /**
     * assignTicket
     *
     * Assign an existing ticket to a user or group
     *
     * @author diego.pessanha
     * @since 2014-10-09
     *
     * @param integer $id ID of the previously created tcket GLPI
     * @param integer $userId ID of the user in GLPI
     * @param integer $groupId ID of the group in GLPI
     * @return boolean
     *
     */
    public function assignTicket($id, $userId, $groupId)
    {
        if ($id == null || ($userId == null && $groupId == null))
        {
            $this->errors[] = "Please, select a valid ticket and a user or a group! Ticket $id was created but could not assig the user/group.";
            return false;
        }       
        
        $params['ticket'] = $id;
        $params['user'] = $userId;
        $params['group'] = $groupId;

        $response = $this->doRequest('glpi.setTicketAssign',$params);
            
        return true;
    }
    
    /**
     * closeTicket
     *
     * Set the status of an existing ticket to "Resolved"
     *
     * @author diego.pessanha
     * @since 2014-10-09
     *
     * @param integer $id ID of the previously created tcket GLPI
     * @param string $message A followup message, mandatory
     * @return boolean
     *
     */
    public function closeTicket($id, $message)
    {
        if ($id == null || $message == null)
        {
            $this->errors[] = "ID and a followup message are required! Ticket $id was created but could not be closed.";
            return false;
        }       
    
        // Set solution
        $params['method'] = 'glpi.addTicketFollowup';
        $params['ticket'] = $id;
        $params['content'] = $message;
        $params['close'] = true;
        
        $response = $this->doRequest('glpi.addTicketFollowup',$params);
            
        // Return webservice response object
        return true;
    }
    
    /**
     * getCategory
     *
     * Get the name of an existing category
     *
     * @author diego.pessanha
     * @since 2014-10-15
     *
     * @param integer $id ID of the previously created tcket GLPI
     * @param string $id The id of the category
     * @return boolean
     *
     */
    public function getCategory($id)
    {
        if ($id == null)
        {
            $this->errors[] = "ID of the category are required!";
            return false;
        }       
        
        $params['id'] = $id;
        $result = $this->getListItems('itilcategories',$id);
            
        if (!empty($result))
        {
            return $result[0];
        }
        else
        {
            return false;
        }
    }
    
    /**
     * getListItem
     *
     * Get the list of the items in an existing category
     *
     * @author diego.pessanha
     * @since 2014-10-15
     *
     * @param integer $id ID of the previously created tcket GLPI
     * @param string $listName The name of the class of special list in GLPI
     * @param string $id The id of the category
     * @return boolean
     *
     */
    public function getListItems($listName, $id = null)
    {
        if ($listName == null)
        {
            $this->errors[] = "Name of the list is required!";
            return false;
        }       
    
        $params['dropdown'] = $listName;            
        if ($id != null) $params['id'] = $id;
        
        $result = $this->doRequest('glpi.listDropdownValues',$params);

        // Return webservice response object
        return $result;
    }
    
    /**
     * uploadDocument
     *
     * Attach a file to an existing ticket. Verify allowed extensions on GLPI.
     *
     * @author diego.pessanha
     * @since 2014-10-15
     *
     * @param integer $id ID of the previously created tcket GLPI
     * @param string $name The name of the document (no spaces and with extension)
     * @param string $docPath The local path to the document
     * @param string $content Follow-up text
     * @return boolean
     *
     */
    public function uploadDocument($id, $name, $docPath, $content = null)
    {
        if ($id == null || ($name == null && $docPath == null))
        {
            $this->errors[] = "ID of the ticket, name of the document and the path to it are required!";
            return false;
        }       
        
        if (!file_exists($docPath))
        {
            $this->errors[] = "The file does not exists!";
            return false;
        }
        
        $base64 = base64_encode(file_get_contents($docPath));
    
        $params['ticket'] = $id;
        $params['name'] = $name;
        $params['base64'] = $base64;
        
        if ($content != null) $params['content'] = $content;
        
        $result = $this->doRequest('glpi.addTicketDocument',$params);
           
        return true;
    }
}
