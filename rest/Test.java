import java.io.BufferedReader;
import java.io.IOException;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;
import java.net.URLEncoder;
import org.json.JSONObject;
 
public class Test
{ 
    public static void main(String[] args) 
    { 
        try {
            System.out.println("\n\n*** GLPI Java Rest Integration ***\n\n");
            System.out.println("@ Connecting to URL:\n");
            System.out.println("http://www.yourglpi.com/plugins/webservices/rest.php?method=glpi.doLogin&login_name=ic_usr&login_password=123456\n\n");
            URL url = new URL("http://www.yourglpi.com/plugins/webservices/rest.php?method=glpi.doLogin&login_name=ic_usr&login_password=123456");
            HttpURLConnection conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setRequestProperty("Accept", "application/json");
     
            if (conn.getResponseCode() != 200) 
            {
                throw new RuntimeException("Failed : HTTP error code : "
                        + conn.getResponseCode());
            }
     
            BufferedReader br = new BufferedReader(new InputStreamReader(
                (conn.getInputStream())));
     
            String output;
            String json = null;
            String session = null;
            System.out.println("@ Output from Server: \n");
            while ((output = br.readLine()) != null) 
            {
                System.out.println(output + "\n\n");
                json = output;
            }

            JSONObject jsonObject = new JSONObject(json);
            session = jsonObject.getString("session");
            System.out.println("@ Received Session: " + session + "\n\n");
        
            url = new URL("http://www.yourglpi.com/plugins/webservices/rest.php?method=glpi.listDropdownValues&dropdown=itilcategories&id=155&session="+session);
            System.out.println("@ Connection to URL:\n");
            System.out.println("http://www.yourglpi.com/plugins/webservices/rest.php?method=glpi.listDropdownValues&dropdown=itilcategories&id=155&session="+session+"\n\n");
            conn = (HttpURLConnection) url.openConnection();
            conn.setRequestMethod("GET");
            conn.setRequestProperty("Accept", "application/json");
     
            if (conn.getResponseCode() != 200) 
            {
                throw new RuntimeException("Failed : HTTP error code : "
                        + conn.getResponseCode());
            }
     
            br = new BufferedReader(new InputStreamReader(
                (conn.getInputStream())));
     
            output = null;
            System.out.println("@ Output from Server: \n");
            while ((output = br.readLine()) != null) 
            {
                System.out.println(output + "\n\n");
            }
     
            conn.disconnect();     
        } 
        catch (MalformedURLException e) 
        {
                e.printStackTrace();
        } 
        catch (IOException e) 
        {
            e.printStackTrace();
        }
    }
}
