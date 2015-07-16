<?php
/**
 * GlpiWebService
 *
 * A class to handle basic integration with GLPI WebServices plugin.
 *
 * @author diego.pessanha
 * @since 2014-10-09
 *
 * @var string $url
 * @var string $ws_user
 * @var string $ws_pass
 * @var string $glpi_user
 * @var string $glpi_pass
 * @var string $error
 * @var string $session
 * @var SoapClient $client
 */
class GlpiWebService
{
    public $url = "http://YOUR-URL/plugins/webservices/soap.php";
    public $ws_user = '';
    public $ws_pass = '';    
    public $glpi_user = '';
    public $glpi_pass = '';
    public $client = null;
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

    /**
     * connect
     *
     * Connect to the WebService and call the doLogin methos if specified
     *
     * @author diego.pessanha
     * @since 2014-10-09
     * @param boolean $autoLogin default true
     * @return boolean
     */
    public function connect($autoLogin = true)
    {
        // Try to connect to GLPI soap server
        try 
        {
            $this->client = new SoapClient(null,array(
                'uri' => $this->url,
                'location' => $this->url
            ));
        } 
        catch (Exception $e) 
        {
            $this->errors[] = 'Could not connect';
            return false;
        }
        
        if ($autoLogin)
        {
            return $this->login();
        }
        
        return true;
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
        if ($this->client)
        {
            // Authenticate
            $params = array(
                'login_name' => $this->glpi_user,
                'login_password' => $this->glpi_pass,    
                'username' => $this->ws_user,
                'password' => $this->ws_pass,
                'method' => 'glpi.doLogin'
            );

            try 
            {
                $response = $this->client->__soapCall('genericExecute', array(new SoapParam($params,'params')));
            }
            catch (Exception $e) 
            {
                $this->errors[] = $e->getMessage();
                return false;
            }

            $this->session = $response['session'];
            return true;
        }
        else
        {
            $this->errors[] = 'SOAP client not connected! Use GlpiWebService->connect() method.';
            return false;
        }
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
    
        if ($this->client)
        {
            $params['method'] = 'glpi.createTicket';

            try 
            {
                $response = $this->client->__soapCall("genericExecute", array(new SoapParam($params,'params')));
            }
            catch (Exception $e) 
            {
                $this->errors[] = $e->getMessage();
                return false;
            }
            
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
        else
        {
            $this->errors[] = 'SOAP client not connected! Use GlpiWebService->connect() method.';
            return false;
        }
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
    
        if ($this->client)
        {
            // Set solution
            $params['method'] = 'glpi.setTicketSolution';
            $params['ticket'] = $id;
            $params['type'] = $solutionType;
            $params['solution'] = $solutionDesc;

            // Invoke webservice method with your parameters
            try 
            {
                $response = $this->client->__soapCall("genericExecute", array(new SoapParam($params,'params')));
            }
            catch (Exception $e) 
            {
                $this->errors[] = $e->getMessage();
                return false;
            }
            
            // Return webservice response object
            return true;
        }
        else
        {
            $this->errors[] = 'SOAP client not connected! Use GlpiWebService->connect() method.';
            return false;
        }
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
    
        if ($this->client)
        {
            $params['method'] = 'glpi.setTicketAssign';
            $params['ticket'] = $id;
            $params['user'] = $userId;
            $params['group'] = $groupId;

            try 
            {
                $response = $this->client->__soapCall("genericExecute", array(new SoapParam($params,'params')));
            }
            catch (Exception $e) 
            {
                $this->errors[] = $e->getMessage();
                return false;
            }
            
            return true;
        }
        else
        {
            $this->errors[] = 'SOAP client not connected! Use GlpiWebService->connect() method.';
            return false;
        }
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
    
        if ($this->client)
        {
            // Set solution
            $params['method'] = 'glpi.addTicketFollowup';
            $params['ticket'] = $id;
            $params['content'] = $message;
            $params['close'] = true;

            // Invoke webservice method with your parameters
            try 
            {
                $response = $this->client->__soapCall("genericExecute", array(new SoapParam($params,'params')));
            }
            catch (Exception $e) 
            {
                $this->errors[] = $e->getMessage();
                return false;
            }
            
            // Return webservice response object
            return true;
        }
        else
        {
            $this->errors[] = 'SOAP client not connected! Use GlpiWebService->connect() method.';
            return false;
        }
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
    
        if ($this->client)
        {
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
        else
        {
            $this->errors[] = 'SOAP client not connected! Use GlpiWebService->connect() method.';
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
    public function getListItems($listName, $id = null, $name = null)
    {
        if ($listName == null)
        {
            $this->errors[] = "Name of the list is required!";
            return false;
        }       
    
        if ($this->client)
        {
            // Set solution
            $params['method'] = 'glpi.listDropdownValues';
            $params['dropdown'] = $listName;
            if ($id != null) $params['id'] = $id;
            if ($name != null) $params['name'] = $name;

            // Invoke webservice method with your parameters
            try 
            {
                $response = $this->client->__soapCall("genericExecute", array(new SoapParam($params,'params')));
            }
            catch (Exception $e) 
            {
                $this->errors[] = $e->getMessage();
                return false;
            }
            
            // Return webservice response object
            return $response;
        }
        else
        {
            $this->errors[] = 'SOAP client not connected! Use GlpiWebService->connect() method.';
            return false;
        }
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
    
        if ($this->client)
        {
            $base64 = base64_encode(file_get_contents($docPath));
        
            // Set solution
            $params['method'] = 'glpi.addTicketDocument';
            $params['ticket'] = $id;
            $params['name'] = $name;
            $params['base64'] = $base64;
            
            if ($content != null) $params['content'] = $content;

            // Invoke webservice method with your parameters
            try 
            {
                $response = $this->client->__soapCall("genericExecute", array(new SoapParam($params,'params')));
            }
            catch (Exception $e) 
            {
                $this->errors[] = $e->getMessage();
                return false;
            }
            
            // Return webservice response object
            return true;
        }
        else
        {
            $this->errors[] = 'SOAP client not connected! Use GlpiWebService->connect() method.';
            return false;
        }
    }
}
