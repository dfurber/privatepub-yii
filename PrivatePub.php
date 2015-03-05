<?php
/**
 * Created by PhpStorm.
 * User: davidfurber
 * Date: 3/4/15
 * Time: 1:30 PM
 */

namespace ext\privatepub;
use \CException;
use \CJSON;
use \Yii;
class PrivatePub extends \CComponent
{
    /**
     * @var string The URL for the Faye server, with path
     */
    public $server;

    /**
     * @var string The secret token for the Faye server.
     */
    public $token;

    /**
     * @var int If the channel should only be valid for a number of seconds.
     */
    public $signatureExpiration;

    /**
     * @var bool Should we use the asset manager to publish the private_pub.js file. Set to false if you will handle this yourself.
     */
    public $publishAssets = true;

    /**
     * @var closure A callback to use before making the CURL request to the Faye server. Useful for adding proxy settings and the like using curl_setopt. Receives and must return the curl object.
     */
    public $curlCallback;

    private $_assetsPublished = false;

    function init()
    {
        if (!$this->server)
            $this->server = @$_ENV['PRIVATE_PUB_SERVER'];
        if (!$this->server)
            throw new CException('Private Pub needs you to set the ENV var PRIVATE_PUB_SERVER with the URL of your Faye server.');
        if (!$this->token)
            $this->token = @$_ENV['PRIVATE_PUB_TOKEN'];
        if (!$this->token)
            throw new CException('Private Pub needs you to set the ENV var PRIVATE_PUB_TOKEN.');
    }

    /**
     * @param $channel string Name of the channel to which to subscribe, written as a URL path starting with /. I.e. /chat/42
     *
     * Call this anywhere to subscribe to a channel. As long as the user has the browser open,
     * any messages published to this channel will be pushed to the user.
     *
     * Yii::app()->privatepub->subscribeTo('/chats/42');
     *
     */
    public function subscribeTo($channel)
    {
        $subscription = CJSON::encode($this->subscription(array('channel' => $channel)));
        $this->registerAssets();
        Yii::app()->getClientScript()->registerScript('privatepub_'.$channel, "PrivatePub.sign($subscription);");
    }

    /**
     * @param $channel string Name of the channel to which to publish, written as a URL path starting with /. I.e. /chat/42
     * @param $data mixed Either a string or a JSON object to send to subscribed browsers.
     * @return mixed
     *
     * Sends a message to subscribers on a given channel. For example, in a model file:
     *
     * function afterSave() {
     *   Yii::app()->privatepub->publishTo('/chats/42', ['name'=>'David', 'message'=>'Yo dude wassup?']);
     *   return parent::afterSave();
     * }
     *
     */
    public function publishTo($channel, $data)
    {
        $message = $this->message($channel, $data);
        return $this->publishMessage($message);
    }

    private function publishMessage($message)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->server);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'message='.CJSON::encode($message));
        if ($this->curlCallback)
            $curl = call_user_func($this->curlCallback, $curl);
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }

    private function message($channel, $data)
    {
        $message = array(
            'channel' => $channel,
            'data' => array('channel' => $channel),
            'ext' => array('private_pub_token' => $this->token),
        );
        if (is_string($data))
            $message['data']['eval'] = $data;
        else
            $message['data']['data'] = $data;
        return $message;
    }

    private function subscription($options = array())
    {
        $sub = array_merge(array(
            'server' => $this->server,
            'timestamp' => time() * 1000,
        ), $options);
        $signature = implode('', array(
            $this->token, $sub['channel'], $sub['timestamp']
        ));
        $sub['signature'] = sha1($signature);
        return $sub;
    }

    private function registerAssets()
    {
        if (!$this->publishAssets && !$this->_assetsPublished)
            return;
        $asset_file = implode(DIRECTORY_SEPARATOR, array(__DIR__, 'assets', 'js', 'private_pub.js'));
        $file = Yii::app()->getAssetManager()->publish($asset_file);
        Yii::app()->getClientScript()->registerScriptFile($file);
        $this->_assetsPublished = true;
    }
}
