<?php
/*
Author: Jibon Lawrence Costa
email: jiboncosta57@gmail.com
License: GNU/GPL
Package: plg_limbomedia
*/

// No direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgUserLimbomedia extends JPlugin
{	
	
	protected $password;
	protected $serverUrl;
	protected $adminUsername;
	protected $adminPass;
	protected $cookies_url;
	
	
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->serverUrl = rtrim($this->params->get('serverUrl'), '/');
		$this->adminUsername = $this->params->get('adminUsername');
		$this->adminPass = $this->params->get('adminPass');
		preg_match('/:\/\/(.*):/', $this->params->get('serverUrl'), $matches);
		$this->cookies_url = $matches[1];
	}
	
	public function onUserBeforeSave($user, $isnew, $new)
	{
		$this->password = $new['password_clear'];
	}	
	
	public function onUserAfterSave($user, $isnew, $success, $msg)
	{
	
		$fields = array(
				'name' => urlencode($user['username']),
				'password' => urlencode($this->password),
				'enable' => urlencode('true'),
				'description' => 'Registered by Joomla',
				'language' => 'en',
				'authorities' => rtrim($this->params->get('authorities'), ','),
			);

		if ($isnew) {
			
			$this->registration($fields);
		}
		else {
			$fields['id'] = $this->getUserID($user['username']);			
			if ($user['block'] == 1){
				unset($fields['enable']);
				$fields['enable'] = 'false';
			}
			$this->registration($fields);
		}
	}

	
	public function onUserAfterDelete($user, $succes, $msg)
	{		
		$id = $this->getUserID($user['username']);
		$this->delete($id);
	}
	
	public function onUserLogin($user, $options)
	{		
		$this->password = $user['password'];
		return;
	}
	
	public function onUserAfterLogin($options)
	{
		$this->login($options['user']->username, $this->password);
		return ;
	}

	public function onUserLogout($user)
	{
		setcookie("JSESSIONID", "", time() - 2678400,"/",$this->cookies_url);
		return ;
	}
	
	protected function readcookies()
	{		
		$file = dirname ( __FILE__ ).DIRECTORY_SEPARATOR."cookie.txt";		
		$myfile = fopen($file, "r");
		$contents = fread($myfile, filesize($file));		
		fclose($myfile);
		preg_match("/JSESSIONID(.*)/", $contents, $match);
		return $match[1];
	}
	
	protected function registration($fields = array())
	{
		
		$url = $this->serverUrl."/api/admin/userCrup";		
		foreach ($fields as $key => $value){
			$fields_string .= $key.'='.$value.'&';
		}
		
		$fields_string = rtrim($fields_string, "&");
		
		$tmpfname = dirname(__FILE__).DIRECTORY_SEPARATOR.'cookie.txt'; // want to store cookies
		
		$ch = curl_init();
		// Let's do a login with ROLE_ADMINISTRATION privilege 
		curl_setopt($ch, CURLOPT_URL, $this->serverUrl.'/api/g/login?username='.$this->adminUsername.'&password='.$this->adminPass);
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
		curl_setopt($ch, CURLOPT_COOKIESESSION, true );
		curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_exec($ch);

		// Now let's send the data for registration
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
		
		curl_exec($ch);
		curl_close($ch);
		unlink($tmpfname);
		return;
	}
	
	protected function login($username, $pass)
	{
		
		$url = $this->serverUrl.'/api/g/login?username='.$username.'&password='.$pass;
		
		$tmpfname = dirname(__FILE__).DIRECTORY_SEPARATOR.'cookie.txt'; // want to store cookies
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
		curl_setopt($ch, CURLOPT_COOKIESESSION, true );
		curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);
		curl_close($ch);
		
		$cookies = $this->readcookies();
		setcookie("JSESSIONID", trim($cookies), time() + 2678400,"/",$this->cookies_url);
		unlink($tmpfname);
		return;
	}

	protected function getUserID($username = null)
	{		
		
		$tmpfname = dirname(__FILE__).DIRECTORY_SEPARATOR.'cookie.txt'; // want to store cookies
		
		$ch = curl_init();
		// Let's do a login with ROLE_ADMINISTRATION privilege 
		curl_setopt($ch, CURLOPT_URL, $this->serverUrl.'/api/g/login?username='.$this->adminUsername.'&password='.$this->adminPass);
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
		curl_setopt($ch, CURLOPT_COOKIESESSION, true );
		curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);
		
		curl_setopt($ch, CURLOPT_URL, $this->serverUrl.'/api/admin/users');
		$users = json_decode(curl_exec($ch));
		curl_close($ch);
		unlink($tmpfname);
		foreach ($users->items as $user){
			if ($user->name == $username){
				$id = $user->id;
				break;
			}
		}
		return $id;
	}
	
	protected function delete ($id = null)
	{
		
		$url = $this->serverUrl."/api/admin/userDelete";		
		
		$fields_string = "id=".$id;
		
		$tmpfname = dirname(__FILE__).DIRECTORY_SEPARATOR.'cookie.txt'; // want to store cookies
		
		$ch = curl_init();
		// Let's do a login with ROLE_ADMINISTRATION privilege 
		curl_setopt($ch, CURLOPT_URL, $this->serverUrl.'/api/g/login?username='.$this->adminUsername.'&password='.$this->adminPass);
		curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
		curl_setopt($ch, CURLOPT_COOKIESESSION, true );
		curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfname);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfname);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);

		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);		
		curl_exec($ch);
		
		curl_close($ch);
		unlink($tmpfname);
		return;
	}
}