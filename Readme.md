# Private Pub Yii Component

Private Pub is a [Ruby gem](https://github.com/ryanb/private_pub) that Ryan Bates created to handle private pubsub in Rails. From the description:

Private Pub is a Ruby gem for use with Rails to publish and subscribe to messages through Faye. It allows you to easily provide real-time updates through an open socket without tying up a Rails process. All channels are private so users can only listen to events you subscribe them to.

Watch [RailsCasts Episode 316](http://railscasts.com/episodes/316-private-pub) for a demonstration of Private Pub.

You will see that private_pub the gem is a bit ambitious in that it contains both the server and client components. Since everybody knows that "Rails doesn't scale" is it a good idea to make your Rails app double as a Faye server? So I removed the server version and replaced it with a separate server that runs on Node.js. The client version I use in Rails projects is [available here as a gem](https://github.com/dfurber/private_pub_client). This repository is a PHP port of that gem into a Yii component.

* Note this is only the client. In order for pubsub to work, you also need a server to act as a relay. We suggest [this one](https://github.com/dfurber/privatepub_server).


### Installation

 * Install via composer or place in your extensions folder.

 * Add the alias to your imports: 'ext.privatepub.*'

 * Add to your Yii components config:
 
```php
'privatepub' => array(
   'class' => 'ext\privatepub\PrivatePub',
   'server' => $_ENV['PRIVATE_PUB_SERVER'],
   'token' => $_ENV['PRIVATE_PUB_SECRET_TOKEN'],
   'publishAssets' => false,
   //'curlCallback' => function($curl) { return $curl; }
),
```

### Configuration

There are 2 things that need to get set: the server and the token. These are used both to know where to send a cURL request and to sign the request. These must be the same as the equivalent variables on the privatepub server. My Yii config example above uses environment variables. One way to get those variables in for development is to use a .env file like so:

.env file contents:

```yaml
PRIVATE_PUB_SECRET_TOKEN: 76asdf87yasdf9879asd87fa987sad9f87asf
PRIVATE_PUB_SERVER: https://myapp.test:3030/faye
```

And in the index.php (or a file included by the index.php):

```php
$env_file = '.env';
if (defined('YII_ENV') && YII_ENV === 'test')
    $env_file .= '.test';
$env_file = dirname(__FILE__) . '/../../../../' . $env_file;
$data = @file_get_contents($env_file);
if (empty($data)) return;
$data = explode("\n", $data);
foreach ($data as $item)
{
    if (empty($item)) continue;
    list($key, $value) = explode(': ', $item);
    if ($value === 'true')
        $value = true;
    else if ($value === 'false')
        $value = false;
    else
        $value = str_replace("'", '', $value);
    $_ENV[$key] = $value;
    putenv("$key=$value");
} 
```

NOTE: The above is a diversion from what you need to do to get the thing working. If you put the server and token config into your local.php, that will be fine. The recommendation to use ENV vars is a shill for the 12 factor app, which makes configuration parameters easier to manage, easier to keep secret, easier to keep out of source control, easier to deploy with Docker, Heroku, Dokku, or just plain Ubuntu. There are several flavors of PHP dotenv.

### Usage

Think of "channels" as URL paths. In the HTTP world, let's say that a comment gets added to a post, and you want to broadcast that comment to anyone who is viewing that post. So you might have a channel called "/posts/42". Users on the web page for that post would subscribe to that channel. On the server, as the post gets updated and comments get added, removed, edited, etc, you publish that event along with enough data for the front end to process it. All in all three steps or concepts:

1. The browser/client *subscribes* to the channel. The following code hooks into the client script manager to load the private pub javascript and subscribe the user to the channel:

```php
Yii::app()->privatepub->subscribeTo('/posts/42');
```

2. The server *publishes* events to the channel. Note that provide enough data so that the front end can react:

```php
function afterSave() {
  Yii::app()->privatepub->publishTo('/posts/'.$this->id, array('action' => 'update_post', 'post' => $this->getAttributes()));
  return parent::afterSave();
}
```
        
3. The browser/client handles the publish event for the channel:

```javascript
function myHandler(json) {
  alert('Yo man I just got called via push!');
  console.log(json.action); // Should be 'update_post' from step 2
  console.log(json.post); // Should have the attributes of the post.
  // some code here might lead up to a $scope.post = json.post ...
}
PrivatePub.subscribe('/posts/42', myHandler);
```
        
### SSL Considerations

If you develop locally with SSL, it should mostly "just work" with a big caveat about self-signed certificates. If you are used to getting the screen that tells you to accept an untrusted certificate, then you will have to do the same thing for your private pub URL. I copy the URL from the env file or from the development console, open in a new tab, accept the certificate, close the tab, refresh the app, and it works.
