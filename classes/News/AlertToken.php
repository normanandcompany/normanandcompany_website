<?php
declare(strict_types=1);

final class NewsAlertToken
{
 public static function create(int $alertId):string
 {
  $secret=(string)getenv('NEWS_UNSUBSCRIBE_SECRET');if(strlen($secret)<32)throw new RuntimeException('NEWS_UNSUBSCRIBE_SECRET must be configured with at least 32 characters.');
  $payload=self::base64Url((string)$alertId);return $payload.'.'.self::base64Url(hash_hmac('sha256',$payload,$secret,true));
 }
 public static function alertId(string $token):?int
 {
  $parts=explode('.',$token,2);if(count($parts)!==2)return null;$secret=(string)getenv('NEWS_UNSUBSCRIBE_SECRET');if(strlen($secret)<32)return null;
  $expected=self::base64Url(hash_hmac('sha256',$parts[0],$secret,true));if(!hash_equals($expected,$parts[1]))return null;
  $decoded=self::base64UrlDecode($parts[0]);return ctype_digit($decoded)?(int)$decoded:null;
 }
 private static function base64Url(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
 private static function base64UrlDecode(string $value):string{return (string)base64_decode(strtr($value,'-_','+/'),true);}
}
