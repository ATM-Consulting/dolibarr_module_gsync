<?php

// After filling in the clientID, clientSecret and redirectUri (within 'config.json'), you should visit this page
// to get the authorisation URL.

// Note that the redirectUri value should point towards a hosted version of 'redirect_handler.php'.
define('INC_FROM_CRON_SCRIPT', 1);
require_once './config.php';
require_once './vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName('GSync Web Google Contacts API');
$client->setClientId($conf->global->GSYNC_CLIENT_ID);
$client->setClientSecret($conf->global->GSYNC_CLIENT_SECRET);
$client->setRedirectUri(dol_buildpath('gsync/redirect-handler.php', 2));
$client->setAccessType('offline');
$client->setApprovalPrompt('force');
$client->setState( strtr(base64_encode(json_encode(array('fk_user' => GETPOST('fk_user')))), '+/=', '-_,') );

$client->setScopes(array(/*
    'https://apps-apis.google.com/a/feeds/groups/',
    'https://www.googleapis.com/auth/userinfo.email',
    'https://apps-apis.google.com/a/feeds/alias/',
    'https://apps-apis.google.com/a/feeds/user/',*/
    'https://www.google.com/m8/feeds/',
    /*'https://www.google.com/m8/feeds/user/',*/
));

$authUrl = $client->createAuthUrl();
header('Location: '.$authUrl);
exit;
