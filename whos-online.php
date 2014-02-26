<?php

/** 
 *  Logs into the libraryh3lp API and checks to see who is online.
 *
 *  Returns a colon-delimited list of users and their availability. 
 * 
 *  Usage from the command line: php whos-online.php <username> <password>
 */

  $username = $argv[1];
  $password = $argv[2];

  $url = 'https://us.libraryh3lp.com/2011-12-03/auth/login';

  $fields = array(
   'username' => $username,
   'password' => $password,  
  );

  $fields_string = '';
  foreach($fields as $key=>$value) {
    $fields_string .= $key . '=' . $value . '&';
  }
  rtrim($fields_string, '&');

  // authenticate

  $ch = curl_init();

  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, count($fields));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
  curl_setopt($ch, CURLOPT_HEADER, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  $result = curl_exec($ch);
  curl_close($ch);

  // isolate the cookie

  preg_match('/^Set-Cookie:\s*([^;]*)/mi', $result, $m);
  $cookie = $m[1];

  // get all users

  $ch = curl_init();

  $url = 'https://us.libraryh3lp.com/2011-12-03/users';
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_COOKIE, $cookie);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  $result = curl_exec($ch);
  $data = json_decode($result);

  // display data

  foreach ($data as $user) {
    echo sprintf("%-25s", $user->name) . ' : ' . $user->show . "\n";
  }

?>
