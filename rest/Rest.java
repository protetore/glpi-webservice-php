import java.io.BufferedReader;
import java.net.URLEncoder
import java.io.IOException;
import java.io.InputStreamReader;
import org.apache.http.HttpResponse;
import org.apache.http.client.ClientProtocolException;
import org.apache.http.client.HttpClient;
import org.apache.http.client.methods.HttpGet;
import org.apache.http.impl.client.DefaultHttpClient;
public class Rest {
 public static void main(String[] args) throws ClientProtocolException, IOException {
  HttpClient client = new DefaultHttpClient();
  HttpGet request = new HttpGet("http://www.yourglpi.com/plugins/webservices/rest.php?" + URLEncoder.encode("method=glpi.doLogin&login_name=glpi_user&login_password=123456", "UTF-8"));
  HttpResponse response = client.execute(request);
  BufferedReader rd = new BufferedReader (new InputStreamReader(response.getEntity().getContent()));
  String line = null;
  while ((line = rd.readLine()) != null) {
    System.out.println(line);
  }
 }
}
