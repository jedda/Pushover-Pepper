<?php

/******************************************************************************
 Pepper

 Developer       : Jedda Wignall
 Developer Site  : http://jedda.me/pushover-pepper-mint
 Pepper Name     : Pushover

 ******************************************************************************/

if (!defined('MINT')) { header('Location:/'); }; // Prevent viewing this file directly
define("API_TOKEN", "cb8tz4C4PvAIemFfDgH6w0HODoL0vz");

$installPepper = "JW_Pushover";

class JW_Pushover extends Pepper {

	var $version    = 100;
	
	var $info       = array (
    	'pepperName'    => 'Pushover',
    	'pepperUrl'     => 'http://jedda.me/pushover-pepper',
   		'pepperDesc'    => 'Sends a notification through Pushover whenever certain events occur.',
    	'developerName' => 'Jedda Wignall',
    	'developerUrl'  => 'http://jedda.me/'
	);
	
	var $prefs = array (
        'user_key' => '',
        'notify_referrer' => true,
        'notify_visitor' => true,
        'notify_views' => true,
        'views_threshold' => 250,
        'minutes_threshold'=> 10
	);
    
    function isCompatible() {
        if (!function_exists("curl_init")) {
            return array('isCompatible' => false, 'explanation' => '<p>Pushover Pepper requires curl support, which is not currently present.</p>');
        } else {
            return array('isCompatible' => true);
        }
    }

	function onDisplayPreferences() {
        $user_key = $this->prefs['user_key'];
		$notify_referrer = ($this->prefs['notify_referrer'] == "1") ? 'checked=true' : '';
		$notify_visitor = ($this->prefs['notify_visitor'] == "1") ? 'checked=true' : '';
        $notify_views = ($this->prefs['notify_views'] == "1") ? 'checked=true' : '';
        $views_threshold = $this->prefs['views_threshold'];
        $minutes_threshold = $this->prefs['minutes_threshold'];

		$preferences['Pushover Settings'] = "
		<table>
			<tr>
				<td scope=\"row\">Pushover User Key:</td>
				<td>
					<span>
						<input type=\"text\" name=\"user_key\" value=\"$user_key\"/>
					</span>
				</td>
			</tr>
		</table>";

		$preferences['Notification Types'] = "
			<table>
				<tr>
					<td scope=\"row\">Notify me on:</td>
					<td>
						<input type=\"checkbox\" id=\"notify_referrer\" value=\"1\" name=\"notify_referrer\" $notify_referrer> <label for=\"notify_referrer\">Unique Referers</label>
					</td>
				</tr>
				<tr>
					<td scope=\"row\">&nbsp;</td>
					<td>
						<input type=\"checkbox\" id=\"notify_visitor\" value=\"1\" name=\"notify_visitor\" $notify_visitor> <label for=\"notify_visitor\">Unique Visitors</label>
					</td>
				</tr>
				<tr>
				    <td scope=\"row\">&nbsp;</td>
				    <td>
						<input type=\"checkbox\" id=\"notify_views\" value=\"1\" name=\"notify_views\" $notify_views> <label for=\"notify_views\">Page Views Exceeding:</label>
					</td>
				</tr>
				<tr>
				    <td scope=\"row\">&nbsp;</td>
				    <td>
						<input type=\"text\" style=\"margin-right:5px; margin-bottom: 5px;\" id=\"views_threshold\" value=\"$views_threshold\" size=\"3\" name=\"views_threshold\"/> views within <br/><input type=\"text\" id=\"minutes_threshold\" style=\"margin-right:5px;\" size=\"3\" value=\"$minutes_threshold\" name=\"minutes_threshold\"/> minutes
					</td>
				</tr>
			</table>";

		return $preferences;
    }
    

    function onSavePreferences() {
        $this->prefs['user_key'] = $_POST['user_key'];
    	$this->prefs['notify_referrer'] = ($_POST['notify_referrer'] == "1") ? 1 : 0;
    	$this->prefs['notify_visitor'] = ($_POST['notify_visitor'] == "1") ? 1 : 0;
    	$this->prefs['notify_views'] = ($_POST['notify_views'] == "1") ? 1 : 0;
    	$this->prefs['views_threshold'] = $_POST['views_threshold'];
    	$this->prefs['minutes_threshold'] = $_POST['minutes_threshold'];
    	$this->data['page_views'] = 0;
    	$this->data['counter_started'] = '';
		$this->data['last_notification_sent'] = '';
	}

	
    function onRecord() {
		$referer = $this->escapeSQL(preg_replace('/#.*$/', '', htmlentities($_GET['referer'])));
		$resource = $this->escapeSQL(preg_replace('/#.*$/', '', htmlentities($_GET['resource'])));	

		// handle a new referer
		if ($this->prefs['notify_referrer'] == "1") {
			$result = $this->query("SELECT COUNT(*) FROM {$this->Mint->db['tblPrefix']}visit WHERE referer = '$referer'");
			if (mysql_result($result, 0, 0) == 0) {
				$this->notifyPushover('[' . $this->Mint->cfg['siteDisplay'] . '] Unique Referrer','Referrer: ' . $this->trim($referer) . "\nURL: " . $resource );	
			}
		}
	
		// handle a new visitor
		if (($this->prefs['notify_visitor'] == "1") && $this->Mint->acceptsCookies && !isset($_COOKIE['MintUnique'])) {
			$IP = $_SERVER['REMOTE_ADDR'];
			$this->notifyPushover('[' . $this->Mint->cfg['siteDisplay'] . '] Unique Visitor','Visitor IP: ' . $IP . "\nURL: " . $resource);
		}

		// handling a page view
		if ($this->prefs['notify_views'] == "1") {		
			$currentMinute = (int)(time()/60);

			// are we counting or resetting?
			if (isset($this->data['counter_started']) && ($currentMinute-$this->data['counter_started']) < $this->prefs['minutes_threshold']) {
				// within threshold - keep counting
				$this->data['page_views']++;
			} else {
				// outside threshold - reset counter
				$this->resetThresholdCounter($currentMinute);
			}
				
			// do we need to notify?
			if ($this->data['page_views'] >= $this->prefs['views_threshold'] && ($currentMinute-$this->data['last_notification_sent']) >= $this->prefs['minutes_threshold']) {
				$this->notifyPushover('[' . $this->Mint->cfg['siteDisplay'] . '] Views Threshold','You have had ' . $this->data['page_views'] . " views in the last " . $this->prefs['minutes_threshold'] . " minutes.");
				$this->data['last_notification_sent'] = $currentMinute;
			}
	
		}

	}
	
	function notifyPushover($title,$message) {
		if (!isset($title)) { $title = 'undefined';}
		if (!isset($message)) { $message = 'undefined';}
		curl_setopt_array($ch = curl_init(), array(
  		CURLOPT_URL => "https://api.pushover.net/1/messages",
  		CURLOPT_POSTFIELDS => array(
 		"token" => API_TOKEN,
  		"user" => $this->prefs['user_key'],
  		"title" => "$title",
  		"message" => "$message",
		)));
		curl_exec($ch);
		curl_close($ch);
	}
	
	function resetThresholdCounter($time) {
		$this->data['counter_started'] = $time;
		$this->data['page_views'] = 1;
	}
	
	function trim($url) {
		return preg_replace("/^http(s)?:\/\/www\.([^.]+\.)/i", "http$1://$2", preg_replace("/\/index\.[^?]+/i", "/", $url));
	}
    
}

?>