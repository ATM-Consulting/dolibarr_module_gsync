<?php
/* Copyright (C) 2020 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_gsync.class.php
 * \ingroup gsync
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsGSync
 */
class ActionsGSync
{
    /**
     * @var DoliDb		Database handler (result of a new DoliDB)
     */
    public $db;

	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
     * @param DoliDB    $db    Database connector
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param array()         $parameters     Hook metadatas (context, etc...)
     * @param CommonObject $object      The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param string       $action      Current action (if set). Generally create or edit or null
     * @param HookManager  $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
	    global $user, $conf;

	    $TContext = explode(':', $parameters['context']);
	    if (in_array('usercard', $TContext) && $user->id == GETPOST('id', 'int'))
        {
            if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
            dol_include_once('gsync/config.php');
            dol_include_once('gsync/class/gsync.class.php');
            dol_include_once('gsync/vendor/autoload.php');

            if ($action == 'gsync_remove_token')
            {
                $gsync = new GSync($this->db);
                if ($gsync->fetchBy($user->id, 'fk_user') > 0)
                {
                    $client = new Google_Client();
                    $client->setApplicationName('GSync Web Google Contacts API');
                    $client->setClientId($conf->global->GSYNC_CLIENT_ID);
                    $client->setClientSecret($conf->global->GSYNC_CLIENT_SECRET);
                    $client->setRedirectUri(dol_buildpath('gsync/redirect-handler.php', 2));
                    $client->setAccessType('offline');
                    $client->setApprovalPrompt('force');

                    $res = $client->revokeToken($gsync->refresh_token);
                    if ($res) $gsync->delete($user);
                }

                header('Location: '.$_SERVER['PHP_SELF'].'?id='.$user->id);
                exit;
            }
        }

		return 0;
	}

	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $user, $langs;

        $TContext = explode(':', $parameters['context']);
        if (in_array('usercard', $TContext) && $user->id == $object->id)
        {
            if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', 1);
            dol_include_once('/gsync/config.php');
            dol_include_once('/gsync/class/gsync.class.php');

            $gsync = new GSync($this->db);
            $gsync->fetchBy($user->id, 'fk_user');
            if (!empty($gsync->id))
            {
                print '<div class="inline-block divButAction"><a class="butAction" title="'.$langs->trans('GSync_current_token', $gsync->refresh_token).'" href="'.dol_buildpath('/gsync/authorise-application.php', 2).'?fk_user='.$object->id.'">'.$langs->trans('GSync_refresh_token').'</a></div>';
                print '<div class="inline-block divButAction"><a class="butActionDelete" title="'.$langs->trans('GSync_remove_token_title').'" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=gsync_remove_token">'.$langs->trans('GSync_remove_token').'</a></div>';
//                print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=gsync_test_token">'.$langs->trans('GSync_test_token').img_info($langs->trans('GSync_test_token_title')).'</a></div>';
            }
            else
            {
                print '<div class="inline-block divButAction"><a class="butAction" href="'.dol_buildpath('/gsync/authorise-application.php', 2).'?fk_user='.$object->id.'">'.$langs->trans('GSync_get_token').'</a></div>';
            }
        }

        return 0;
    }

//    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
//    {
//        global $user, $langs;
//
//        if (
//            in_array('usercard', explode(':', $parameters['context']))
//            && $user->id == $object->id
//        )
//        {
//            define('INC_FROM_DOLIBARR', 1);
//            dol_include_once('/gsync/config.php');
//
//            if (true)
//            {
//                $button = '<a href="'.dol_buildpath('/gsync/authorise-application.php', 2).'?fk_user='.$object->id.'">'.$langs->trans('GetYourToken').'</a>';
//            }
//            else
//            {
////                $button = '<a href="'.dol_buildpath('/gsync/authorise-application.php',2).'?fk_user='.$object->id.'">'.$langs->trans('UserHasToken').'</a>'.img_info('Token : '.$token->token.' - Refresh : '.$token->refresh_token);
////                $button .=' <a href="'.dol_buildpath($user_card_url,1).'?id='.$object->id.'&action=removeMyToken">'.$langs->trans('Remove').'</a>'.img_info($langs->trans('RemoveToken'));
////                $button .=' <a href="'.dol_buildpath($user_card_url,1).'?id='.$object->id.'&action=testTokenGoogle">'.$langs->trans('Test').'</a>'.img_info($langs->trans('TestToken'));
//            }
//
//            echo '
//					<tr><td>'.$langs->trans('TokenForUser').'</td><td>'.$button.'</td></tr>
//				';
//        }
//    }
}
