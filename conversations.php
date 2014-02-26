<?php
/** 
 *  Logs into the libraryh3lp API and downloads today's transcripts.
 *
 *  Loads todays metdata and transcripts into an array of objects.
 * 
 *  Usage from the command line: php conversations.php <username> <password>
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

  // get converations
  $ch = curl_init();

  // you may want to watch your time zone here
  $url = 'https://us.libraryh3lp.com/2011-12-03/conversations/' . date('Y/m/d') . '?format=json'; 
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_COOKIE, $cookie);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  $result = curl_exec($ch);
  curl_close($ch);

  $records = json_decode($result);
  $ids = array();

  // process records
  foreach ($records as &$record) {
    $localstart = strtotime(substr($record->started, 0, 19));
    $fileDate = date('Y-m-d.His', $localstart);
    $record->transcriptFile = $record->queue . '/' . $record->guest . '/' . $fileDate . '.xml.txt';
    $ids[] = $record->id;
  }
  $id_string = implode(',', $ids);

  $filename = 'libraryh3lp' . substr(md5($id_string), 0, 8) . '.zip'; // using md5 as a quick way to make a unique name

  // get the conversations in a zip file
  $ch = curl_init();
  $url = 'https://us.libraryh3lp.com/2011-12-03/conversations/archive?ids=' . $id_string;
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_COOKIE, $cookie);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  $result = curl_exec($ch); 
  curl_close($ch);

  // write the file; using CURLOPT_FILE resulted in an unreadable zip file
  $fp = fopen(dirname(__FILE__) . '/' . $filename, 'w+');
  fwrite($fp, $result);
  fclose($fp);

  $path = pathinfo(realpath($filename), PATHINFO_DIRNAME);

  // read the zip file
  $zip = new ZipArchive;
  $res = $zip->open($filename);
  if ($res === TRUE) {
    // create an index of file sizes
    $index = array();
    for($i = 0; $i < $zip->numFiles; $i++) {
      $stat = $zip->statIndex($i);
      $index{$zip->getNameIndex($i)} = $stat['size'];
    }
    // read the files from the archive
    foreach ($records as &$record) {
      $zh = $zip->getStream($record->transcriptFile);
      $record->transcript = fread($zh, $index[$record->transcriptFile]);
      fclose($zh);
    }
  } else {
    echo "Error: couldn't open $filename\n" . $res . "\n";
  }

  // clean up: delete the zip file
  unlink($filename);

  // do something with the records
  print_r($records); 
?>
