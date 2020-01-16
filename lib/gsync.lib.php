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
 *	\file		lib/gsync.lib.php
 *	\ingroup	gsync
 *	\brief		This file is an example module library
 *				Put some comments here
 */

/**
 * @return array
 */
function gsyncAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load('gsync@gsync');

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/gsync/admin/gsync_setup.php", 1);
    $head[$h][1] = $langs->trans("Parameters");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/gsync/admin/gsync_extrafields.php", 1);
    $head[$h][1] = $langs->trans("ExtraFields");
    $head[$h][2] = 'extrafields';
    $h++;
    $head[$h][0] = dol_buildpath("/gsync/admin/gsync_about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@gsync:/gsync/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@gsync:/gsync/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'gsync');

    return $head;
}

/**
 * Return array of tabs to used on pages for third parties cards.
 *
 * @param 	GSync	$object		Object company shown
 * @return 	array				Array of tabs
 */
function gsync_prepare_head(GSync $object)
{
    global $langs, $conf;
    $h = 0;
    $head = array();
    $head[$h][0] = dol_buildpath('/gsync/card.php', 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans("GSyncCard");
    $head[$h][2] = 'card';
    $h++;
	
	// Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@gsync:/gsync/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@gsync:/gsync/mypage.php?id=__ID__');   to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'gsync');
	
	return $head;
}

/**
 * @param Form      $form       Form object
 * @param GSync  $object     GSync object
 * @param string    $action     Triggered action
 * @return string
 */
function getFormConfirmGSync($form, $object, $action)
{
    global $langs, $user;

    $formconfirm = '';

    if ($action === 'valid' && !empty($user->rights->gsync->write))
    {
        $body = $langs->trans('ConfirmValidateGSyncBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmValidateGSyncTitle'), $body, 'confirm_validate', '', 0, 1);
    }
    elseif ($action === 'accept' && !empty($user->rights->gsync->write))
    {
        $body = $langs->trans('ConfirmAcceptGSyncBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmAcceptGSyncTitle'), $body, 'confirm_accept', '', 0, 1);
    }
    elseif ($action === 'refuse' && !empty($user->rights->gsync->write))
    {
        $body = $langs->trans('ConfirmRefuseGSyncBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmRefuseGSyncTitle'), $body, 'confirm_refuse', '', 0, 1);
    }
    elseif ($action === 'reopen' && !empty($user->rights->gsync->write))
    {
        $body = $langs->trans('ConfirmReopenGSyncBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmReopenGSyncTitle'), $body, 'confirm_refuse', '', 0, 1);
    }
    elseif ($action === 'delete' && !empty($user->rights->gsync->write))
    {
        $body = $langs->trans('ConfirmDeleteGSyncBody');
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmDeleteGSyncTitle'), $body, 'confirm_delete', '', 0, 1);
    }
    elseif ($action === 'clone' && !empty($user->rights->gsync->write))
    {
        $body = $langs->trans('ConfirmCloneGSyncBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmCloneGSyncTitle'), $body, 'confirm_clone', '', 0, 1);
    }
    elseif ($action === 'cancel' && !empty($user->rights->gsync->write))
    {
        $body = $langs->trans('ConfirmCancelGSyncBody', $object->ref);
        $formconfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ConfirmCancelGSyncTitle'), $body, 'confirm_cancel', '', 0, 1);
    }

    return $formconfirm;
}
