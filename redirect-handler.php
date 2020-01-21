<?php

// This page handles the redirect from the authorisation page. It will authenticate your app and
// retrieve the refresh token which is used for long term access to Google Contacts. You should
// add this refresh token to the 'config.json' file.
define('INC_FROM_CRON_SCRIPT', 1);
require_once './config.php';
dol_include_once('gsync/class/gsync.class.php');

if (!isset($_GET['code'])) {
    die('No code URL paramete present.');
}

$code = $_GET['code'];
$state = json_decode(base64_decode(strtr($_GET['state'], '-_,', '+/=')), 1);

require_once './vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName('GSync Web Google Contacts API');
$client->setClientId($conf->global->GSYNC_CLIENT_ID);
$client->setClientSecret($conf->global->GSYNC_CLIENT_SECRET);
$client->setRedirectUri(dol_buildpath('gsync/redirect-handler.php', 2));
$client->setAccessType('offline');
$client->setApprovalPrompt('force');

$accessToken = $client->authenticate($code);

if (!isset($accessToken['refresh_token'])) {
    dol_syslog('Google did not respond with a refresh token. You can still use the Google Contacts API, but you may to re-authorise your application in the future. ');
    dol_syslog('Access token response:');
    dol_syslog(print_r($accessToken));

    // TODO à modifier car je suis en INC_FROM_CRON_SCRIPT du coup la session n'est pas chargé
    setEventMessage($langs->trans('GSync_refresh_token_error', $accessToken['error'], $accessToken['error_description']), 'errors');
    header('Location: '.dol_buildpath('user/list.php', 1));
    exit;
} else {
    $user->fetch($state['fk_user']);

    $gsync = new GSync($db);
    $gsync->fetchBy($state['fk_user'], 'fk_user', false);
    $gsync->fk_user = $state['fk_user'];
    $gsync->refresh_token = $accessToken['refresh_token'];
    $gsync->access_token = $accessToken;
    $gsync->save($user);

    // TODO à modifier car je suis en INC_FROM_CRON_SCRIPT du coup la session n'est pas chargé
    setEventMessage($langs->trans('GSync_refresh_token_success'));

    header('Location: '.dol_buildpath('user/card.php', 1).'?id='.$state['fk_user']);
    exit;
}
