<?php

namespace podmail;

$userState = $config['session']->get('state');
$httpState = $request->getParam('state');
if ($userState != $httpState) return $response->withStatus(302)->withRedirect('/logout');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://login.eveonline.com/oauth/token');
curl_setopt($ch, CURLOPT_USERAGENT, 'podmail - ' . $config['domain']);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . base64_encode($config['ccp']['clientID'] . ':' . $config['ccp']['secretKey'])]);
curl_setopt($ch, CURLOPT_POST, 2);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=authorization_code&code=' . $request->getParam('code'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
$result = curl_exec($ch);
$tokens = json_decode($result, true);
if (!isset($tokens['access_token']) || !isset($tokens['refresh_token'])) return $response->withStatus(302)->withRedirect('/logout');

$refresh_token = $tokens['refresh_token'];
$ch = curl_init();
// Get the Character details from SSO
curl_setopt($ch, CURLOPT_URL, 'https://login.eveonline.com/oauth/verify');
curl_setopt($ch, CURLOPT_USERAGENT, 'podmail - ' . $config['domain']);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokens['access_token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
$result = curl_exec($ch);
$info = json_decode($result, true);

$char_id = (int) $info['CharacterID'];
$char_name = $info['CharacterName'];
$scopes = $info['Scopes'];

$redis = $config['redis'];
$redis->setex("podmail:last_seen:$char_id", 604800, time());
$redis->setex("podmail:iterate:$char_id", 86400, "true");
Info::addChar($config['db'], $char_id, $char_name);
Info::addScopes($config['db'], $char_id, $scopes, $refresh_token);

Log::log("$char_id - $char_name just logged in.");
$config['session']->set('char_id', $char_id);
$config['true_session']->regenerateId();
return $response->withStatus(302)->withRedirect('/');
