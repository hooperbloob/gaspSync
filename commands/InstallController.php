<?php
/**
* @link http://www.yiiframework.com/
* @copyright Copyright (c) 2008 Yii Software LLC
* @license http://www.yiiframework.com/license/
*/

namespace app\commands;

use yii\console\Controller;

/**
* This command configure gaspSync
*
* Run it !
*
* @author Qiang Xue <qiang.xue@gmail.com>
* @since 2.0
*/
class InstallController extends Controller
{
  /**
  * This command echoes what you have entered as the message.
  * @param string $message the message to be echoed.
  */
  public function actionIndex($message = 'hello world')
  {

    echo "\ngaspSync Installation(based on yii advanced template v1.0)";

    if (!extension_loaded('mcrypt')) {
      die('The mcrypt PHP extension is required by Yii2.');
    }

    $root = str_replace('\\', '/', __DIR__);

    $local='./config/local.init.php';

    if (!is_file($local)){

      echo "\n  Enter the public URI of the server without ending:\n";
      $data['publicURI'] = trim(fgets(STDIN));
      echo "\n  Information for the database connexion (/conf/db.php): ";
      echo "\n  --------------------";
      echo "\n  Host:  ";
      $data['host'] = trim(fgets(STDIN));
      echo "\n  Database Name:  ";
      $data['database'] = trim(fgets(STDIN));
      echo "\n  Username:  ";
      $data['username'] = trim(fgets(STDIN));
      echo "\n  Password:  ";
      $data['password'] = trim(fgets(STDIN));
    }
    else{
      $data=require($local);
    }
    $db=var_export([
      'class' => 'yii\db\Connection',
      'dsn' => 'mysql:host='.$data['host'].';dbname='.$data['database'],
      'username' => $data['username'],
      'password' => $data['password'],
      'charset' => 'utf8',
    ],true);
    $fileContent="<?php\n return $db \n?>";
    echo "\n      editing config/db.php";
    file_put_contents('./config/db.php',$fileContent);


    setPublicURI($data['publicURI']);
    setIssuer($data['publicURI']);

    $root='.';
    setWritable($root,['runtime','web/assets','storage']);

    //Storage paths
    $target=[
        'BrowserID',
        'BrowserID/keys',
        //'BrowserID/keys/tests',
        'BrowserID/var',
        'BrowserID/well-known'
        ];
    foreach ($target as $rep){
      $path=\Yii::getAlias('@storage/'.$rep);
      echo "\n      mkdir $path";
      if (!is_dir($path)){
        mkdir($path, 0777, true);
      }
    }
    setCookieValidationKey('config',['web.php']);

    if (!is_file('./storage/secretToken')){
      echo "\n     creating new secretToken";
      $length = 64;
      $bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
      $secretKey = strtr(substr(base64_encode($bytes), 0, $length), '+/=', '_-.');
      file_put_contents('./storage/secretToken',$secretKey);
    }

    echo "\n      Creating dsaKeyPair";
    $dsa=new \Crypto\Crypto();
    $keyPath=\Yii::getAlias('@storage/BrowserID/keys/');
    $dsa->generateNewDSAKey($keyPath);




    echo "\n";
  }
}

function setWritable($root, $paths)
{
  foreach ($paths as $writable) {
    echo "\n      chmod 0777 $writable";
    @chmod("$root/$writable", 0777);
  }
}

function setExecutable($root, $paths)
{
  foreach ($paths as $executable) {
    echo "\n      chmod 0755 $executable\n";
    @chmod("$root/$executable", 0755);
  }
}

function setCookieValidationKey($root, $paths)
{
  foreach ($paths as $file) {
    echo "\n      generate cookie validation key in $file";
    $file = $root . '/' . $file;
    $length = 32;
    $bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
    $key = strtr(substr(base64_encode($bytes), 0, $length), '+/=', '_-.');
    $content = preg_replace('/(("|\')cookieValidationKey("|\')\s*=>\s*)(""|\'[A-Za-z0-9._:\/-]*\')/', "\\1'$key'", file_get_contents($file));
    file_put_contents($file, $content);
  }
}

function createSymlink($links)
{
  foreach ($links as $link => $target) {
    echo "    symlink $target as $link\n";
    if (!is_link($link)) {
      symlink($target, $link);
    }
  }
}

function setPublicURI($uri){
  $file =  './config/params.php';
  $content = preg_replace('/(("|\')publicURI("|\')\s*=>\s*)(""|\'[A-Za-z0-9._:\/]*\')/', "\\1'$uri'", file_get_contents($file));
  file_put_contents($file, $content);
}

function setIssuer($uri){
  $file =  './config/params.php';
  $data=parse_url($uri);

  $content = preg_replace('/(("|\')assertionIssuer("|\')\s*=>\s*\[)(""|[A-Z,\'a-z0-9._:\/]*)(\])/', "\\1'localhost','".$data['host']."']", file_get_contents($file));
  file_put_contents($file, $content);
}

?>