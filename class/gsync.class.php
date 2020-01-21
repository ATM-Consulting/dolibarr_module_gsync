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

if (!class_exists('SeedObject'))
{
	/**
	 * Needed if $form->showLinkedObjectBlock() is call or for session timeout on our module page
	 */
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__).'/../config.php';
}


class GSync extends SeedObject
{
    /** @var string $table_element Table name in SQL */
    public $table_element = 'gsync';

    /** @var string $element Name of the element (tip for better integration in Dolibarr: this value should be the reflection of the class name with ucfirst() function) */
    public $element = 'gsync';

    /** @var int $isextrafieldmanaged Enable the fictionalises of extrafields */
    public $isextrafieldmanaged = 0;

    /** @var int $ismultientitymanaged 0=No test on entity, 1=Test with field entity, 2=Test with link by societe */
    public $ismultientitymanaged = 0;

    /**
     *  'type' is the field format.
     *  'label' the translation key.
     *  'enabled' is a condition when the field must be managed.
     *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). Using a negative value means field is not shown by default on list but can be selected for viewing)
     *  'noteditable' says if field is not editable (1 or 0)
     *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
     *  'default' is a default value for creation (can still be replaced by the global setup of default values)
     *  'index' if we want an index in database.
     *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
     *  'position' is the sort order of field.
     *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
     *  'isameasure' must be set to 1 if you want to have a total on list for this field. Field type must be summable like integer or double(24,8).
     *  'css' is the CSS style to use on field. For example: 'maxwidth200'
     *  'help' is a string visible as a tooltip on field
     *  'comment' is not used. You can store here any text of your choice. It is not used by application.
     *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
     *  'arraykeyval' to set list of value if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel")
     */

    public $fields = array(


        'fk_user' => array(
            'type' => 'integer',
            'label' => 'UserId',
            'enabled' => 1,
            'visible' => 0,
            'notnull' => 1,
            'index' => 1,
            'position' => 10
        ),

        'refresh_token' => array(
            'type' => 'varchar(255)',
            'label' => 'RefreshToken',
            'enabled' => 1,
            'visible' => 0,
            'position' => 20
        ),

        'access_token' => array(
            'type' => 'array',
            'label' => 'AccessToken',
            'visible' => 0,
            'enabled' => 1,
            'position' => 30
        )

    );

    /** @var string $fk_user User id */
    public $fk_user;

    /** @var string $refresh_token token as string */
    public $refresh_token;

    /** @var array $access_token serialized access token */
    public $access_token;


    /**
     * GSync constructor.
     * @param DoliDB    $db    Database connector
     */
    public function __construct($db)
    {
        parent::__construct($db);

        $this->init();
    }

    /**
     * @param User $user User object
     * @return int
     */
    public function save($user)
    {
        return $this->create($user);
    }

    /**
     * @param User $user User object
     * @return int
     */
    public function delete(User &$user)
    {
        // Les objets GSyncPeople ne sont pas liés directement pour des raisons de performance, il faut donc les delete manuellements
        $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'gsync_people WHERE fk_user = '.$this->fk_user;
        $this->db->query($sql);

        $this->deleteObjectLinked();

        unset($this->fk_element); // avoid conflict with standard Dolibarr comportment
        return parent::delete($user);
    }

    /**
     * @param int $limit Limit number of sync by user
     * @return int
     */
    public function cronjob_nyancat($limit)
    {
        dol_include_once('gsync/vendor/autoload.php');
        $this->output = '['.date('Y-m-d H:i:s').'] - START'."\n";

        /** @var GSync[] $TGSync */
        $TGSync = $this->fetchAll(0, false);
        foreach ($TGSync as $gsync)
        {
            $gsync->syncAllObject($limit);
            $this->output.= $gsync->output;
        }

        $this->output.= '['.date('Y-m-d H:i:s').'] - END'."\n";
        return 0;
    }

    /**
     * @param int $limit Limit number of sync
     * @return void
     */
    protected function syncAllObject($limit = 50)
    {
        global $conf;

        $user = new User($this->db);
        // TODO manage error
        $user->fetch($this->fk_user);
        $user->getrights('societe'); // Pour savoir s'il a le droit "Étendre l'accès à tous les tiers"

        $gsync_people = new GSyncPeople($this->db);
        $TGSyncPeople = $gsync_people->fetchAll($limit, false, array('fk_user' => $this->fk_user, 'to_sync' => 1));
        if (empty($TGSyncPeople)) return; // Nothing to sync, go to next user

        $this->output.= '- Sync user id = '.$user->id.' ('.$user->lastname.' '.$user->firstname.')'."\n";

        $scopes = array(
//            'https://www.googleapis.com/auth/userinfo.profile'
            'https://www.googleapis.com/auth/contacts'
//            , 'https://www.googleapis.com/auth/contacts.readonly'
        );

        // TODO auto refresh token if expired
        $googleOAuth2Handler = new RapidWeb\GoogleOAuth2Handler\GoogleOAuth2Handler($conf->global->GSYNC_CLIENT_ID, $conf->global->GSYNC_CLIENT_SECRET, $scopes, $this->refresh_token);
        $people = new RapidWeb\GooglePeopleAPI\GooglePeople($googleOAuth2Handler);

        foreach ($TGSyncPeople as $gsync_people)
        {
            $this->output.= ' element = '.$gsync_people->element_object.'; fk_object = '.$gsync_people->fk_object."\n";

            $gsync_people->sync($user, $people);

            $this->output.= $gsync_people->output;
        }
    }
}


class GSyncPeople extends SeedObject
{
    /** @var string $table_element Table name in SQL */
    public $table_element = 'gsync_people';

    /** @var string $element Name of the element (tip for better integration in Dolibarr: this value should be the reflection of the class name with ucfirst() function) */
    public $element = 'gsync_people';

    /** @var int $isextrafieldmanaged Enable the fictionalises of extrafields */
    public $isextrafieldmanaged = 0;

    /** @var int $ismultientitymanaged 0=No test on entity, 1=Test with field entity, 2=Test with link by societe */
    public $ismultientitymanaged = 0;

    /**
     *  'type' is the field format.
     *  'label' the translation key.
     *  'enabled' is a condition when the field must be managed.
     *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). Using a negative value means field is not shown by default on list but can be selected for viewing)
     *  'noteditable' says if field is not editable (1 or 0)
     *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
     *  'default' is a default value for creation (can still be replaced by the global setup of default values)
     *  'index' if we want an index in database.
     *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
     *  'position' is the sort order of field.
     *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
     *  'isameasure' must be set to 1 if you want to have a total on list for this field. Field type must be summable like integer or double(24,8).
     *  'css' is the CSS style to use on field. For example: 'maxwidth200'
     *  'help' is a string visible as a tooltip on field
     *  'comment' is not used. You can store here any text of your choice. It is not used by application.
     *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
     *  'arraykeyval' to set list of value if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel")
     */

    public $fields = array(

        'fk_user' => array(
            'type' => 'integer',
            'label' => 'UserId',
            'enabled' => 1,
            'visible' => 0,
            'notnull' => 1,
            'index' => 1,
            'position' => 10
        ),

        'resource_name' => array(
            'type' => 'varchar(255)',
            'label' => 'ResourceName',
            'enabled' => 1,
            'visible' => 0,
            'notnull' => 1,
            'position' => 20
        ),

        'fk_object' => array(
            'type' => 'integer',
            'label' => 'ObjectId',
            'visible' => 0,
            'enabled' => 1,
            'notnull' => 1,
            'index' => 1,
            'position' => 30
        ),

        'element_object' => array(
            'type' => 'varchar(255)',
            'label' => 'ObjectType',
            'enabled' => 1,
            'visible' => 0,
            'notnull' => 1,
            'position' => 40
        ),

        'to_sync' => array(
            'type' => 'integer',
            'label' => 'ToSync',
            'visible' => 0,
            'enabled' => 1,
            'position' => 50
        ),

    );

    /** @var string $fk_user User id */
    public $fk_user;

    /** @var string $resource_name Id of object from Google */
    public $resource_name;

    /** @var int $fk_object Object id */
    public $fk_object;

    /** @var string $element_object Object element */
    public $element_object;

    /** @var int $to_sync Value 0 or 1 to sync or not */
    public $to_sync;


    /**
     * GSync constructor.
     * @param DoliDB    $db    Database connector
     */
    public function __construct($db)
    {
        parent::__construct($db);

        $this->init();
    }

    /**
     * @param User $user User object
     * @return int
     */
    public function save($user)
    {
        return $this->create($user);
    }

    /**
     * @param User $user User object
     * @return int
     */
    public function delete(User &$user)
    {
        $this->deleteObjectLinked();

        unset($this->fk_element); // avoid conflict with standard Dolibarr comportment
        return parent::delete($user);
    }

    /**
     *	Get object and children from database on custom field
     *
     *	@param      string		$key       		key of object to load
     *	@param      string		$field       	field of object used to load
     * 	@param		bool		$loadChild		used to load children from database
     *	@return     int         				>0 if OK, <0 if KO, 0 if not found
     */
    public function fetchFullBy($TFieldValue, $loadChild = true)
    {

        if (empty($TFieldValue)) return false;

        $sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.$this->table_element.' WHERE 1';
        foreach ($TFieldValue as $field => $value)
        {
            $sql.= ' AND '.$field.' = '.$this->quote($value, $this->fields[$field]);
        }
        $sql.= ' LIMIT 1';
        $resql = $this->db->query($sql);

        if(! $resql)
        {
            $this->error = $this->db->lasterror();
            $this->errors[] = $this->error;
            return -1;
        }

        $objp = $this->db->fetch_object($resql);
        if (!$objp) return 0;

        $res = $this->fetchCommon($objp->rowid);
        if($res > 0 && ! empty($loadChild))
        {
            $this->fetchChild();
        }

        return $res;
    }

    /**
     * @param int $fk_user User id to test
     * @return bool|User
     */
    public static function getUserToTest($fk_user)
    {
        global $TUserTmpGSync, $db, $user;

        if ($user->id == $fk_user) return $user;
        if (!empty($TUserTmpGSync[$fk_user])) return $TUserTmpGSync[$fk_user];

        $u = new User($db);
        if ($u->fetch($fk_user) > 0)
        {
            $u->getrights('societe');
            $TUserTmpGSync[$fk_user] = $u;

            return $u;
        }

        if (!empty($u->error)) dol_print_error($u->db, $u->error);

        return false; // User not found or SQL error
    }

    /**
     * @param int $fk_object Object id
     * @param string $element_object Element type
     * @param int $fk_user User id to test
     * @return bool
     */
    public static function allowedToSync($fk_object, $element_object, $fk_user)
    {
        global $db, $conf;

        $userToTest = self::getUserToTest($fk_user);

        if ($element_object == 'company' || $element_object == 'societe' || $element_object == 'contact')
        {
            $canSync = false;

            // Si utilisateur ayant le droit "Étendre l'accès à tous les tiers" ET n'est pas externe ET que la conf du module ne force pas la limite à l'association en tant que commercial du tiers
            if (!empty($userToTest->rights->societe->client->voir) && empty($userToTest->contactid) && empty($conf->global->GSYNC_ONLY_ATTACHED_AS_COMMERCIAL)) $canSync = true;
            else
            {
                if ($element_object == 'company' || $element_object == 'societe')
                {
                    if (!class_exists('Societe')) require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
                    $societe = new Societe($db);
                    $societe->fetch($fk_object);
                    $listsalesrepresentatives=$societe->getSalesRepresentatives($userToTest);
                }
                else if ($element_object == 'contact')
                {
                    if (!class_exists('Contact')) require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
                    $contact = new Contact($db);
                    $contact->fetch($fk_object);
                    $contact->fetch_thirdparty();
                    if (empty($contact->thirdparty->id)) return false; // Contact sans Tiers
                    $listsalesrepresentatives=$contact->thirdparty->getSalesRepresentatives($userToTest);
                }

                // Si la variable existe, alors il faut tester son contenu même si vide
                if (isset($listsalesrepresentatives))
                {
                    foreach ($listsalesrepresentatives as $info)
                    {
                        if ($userToTest->id == $info['id'])
                        {
                            $canSync = true;
                            break;
                        }
                    }
                }
            }

            return $canSync;
        }
        else if ($element_object == 'user_object')
        {
            // Tentative de synchro d'une fiche user avec lui même (pas vraiment de sens de synchro avec soit même)
            if ($fk_user == $fk_object) return false;
            else
            {
                $userObject = new User($db);
                $userObject->fetch($fk_user);
                if ($userToTest->entity == $userObject->entity) return true;
            }
        }

        return false;
    }

    /**
     * @param User $user
     * @param RapidWeb\GooglePeopleAPI\GooglePeople $people
     * @return int
     */
    public function sync($user, $people)
    {
        $object = $this->getObject();
        if (!is_object($object)) return -1;

        $to_sync = GSyncPeople::allowedToSync($object->id, $object->element, $user->id);
        if (!$to_sync)
        {
            $this->output.= ' WARNING sync not allowed, record deleted'."\n";
            $this->delete($user); // Delete reliquat
            return 0;
        }

        if (!empty($this->resource_name))
        {
            try {
                $gcontact = $people->get($this->resource_name); // @see https://developers.google.com/people/api/rest/v1/people/get
            } catch (Exception $e) {
                $obj = json_decode($e->getMessage());
                if ($obj->error->code == 404 && $obj->error->status == 'NOT_FOUND')
                {
                    $gcontact = new RapidWeb\GooglePeopleAPI\Contact($people);
                }
                else
                {
                    $this->output.= ' ERROR get() record failed, message = '.$obj->error->code.' - '.$obj->error->status.': '.$obj->error->message."\n";
                    $this->warnings[] = '['.$obj->error->code.'] '.$obj->error->status.': '.$obj->error->message;
                    return 0;
                }
            }
        }
        else
        {
            $gcontact = new RapidWeb\GooglePeopleAPI\Contact($people);
        }


        if ($this->element_object == 'societe') $this->initGContactWithSociete($gcontact, $object);
        elseif ($this->element_object == 'contact') $this->initGContactWithContact($gcontact, $object);
        else $this->initGContactWithUser($gcontact, $object);


        $this->initGContactMemberships($gcontact, $user);

        try {
            // @see https://developers.google.com/people/api/rest/v1/people/createContact
            // @see https://developers.google.com/people/api/rest/v1/people/updateContact
            $gcontact->save();

            $this->resource_name = $gcontact->resourceName;
            $this->to_sync = 0;
            $this->save($user);
            $this->output.= ' OK resource = '.$gcontact->resourceName."\n";
        } catch (Exception $e) {
            $this->output.= ' ERROR save() record failed, message = '.$e->getMessage()."\n";
            $obj = json_decode($e->getMessage());
            $this->warnings[] = '['.$obj->error->code.'] '.$obj->error->status.': '.$obj->error->message;
            return 0;
        }
    }

    /**
     * @return Societe|Contact|User|int
     */
    protected function getObject()
    {
        if ($this->element_object == 'societe')
        {
            require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
            $object = new Societe($this->db);
            $object->fetch($this->fk_object);
        }
        elseif ($this->element_object == 'contact')
        {
            require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
            $object = new Contact($this->db);
            $object->fetch($this->fk_object);
        }
        elseif ($this->element_object == 'user')
        {
            require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
            $object = new User($this->db);
            $object->fetch($this->fk_object);
        }
        else
        {
            return -1;
        }

        return $object;
    }

    /**
     * @param RapidWeb\GooglePeopleAPI\Contact $gcontact Object
     * @param Societe $object Object
     * @return void
     */
    protected function initGContactWithSociete(&$gcontact, $object)
    {
        // @see https://developers.google.com/people/api/rest/v1/people#Person.Organization
        if (empty($gcontact->organizations[0])) $gcontact->organizations[0] = new stdClass();
        $gcontact->organizations[0]->type = 'work';
        $gcontact->organizations[0]->name = $object->nom;

        // TODO complete ...
    }

    /**
     * @param RapidWeb\GooglePeopleAPI\Contact $gcontact Object
     * @param Contact $object Object
     * @return void
     */
    protected function initGContactWithContact(&$gcontact, $object)
    {
        if (empty($object->thirdparty)) $object->fetch_thirdparty();

        // @see https://developers.google.com/people/api/rest/v1/people#Person.Organization
        if (empty($gcontact->organizations[0])) $gcontact->organizations[0] = new stdClass();
        $gcontact->organizations[0]->type = 'work';
        $gcontact->organizations[0]->name = $object->thirdparty->nom;

        // @see https://developers.google.com/people/api/rest/v1/people#Person.Name
        if (empty($gcontact->names[0])) $gcontact->names[0] = new stdClass();
        $gcontact->names[0]->familyName = $object->lastname;
        $gcontact->names[0]->givenName = $object->firstname;
        if (empty($gcontact->names[0]->metadata)) $gcontact->names[0]->metadata = new stdClass();
        $gcontact->names[0]->metadata->primary = true;

        // @see https://developers.google.com/people/api/rest/v1/people#Person.Gender
        if (empty($gcontact->genders[0])) $gcontact->genders[0] = new stdClass();
        if (in_array($object->civility_code, array('MME', 'MLE'))) $gcontact->genders[0]->value = 'female'; // male, female, other, unknown
        elseif (in_array($object->civility_code, array('MR', 'M'))) $gcontact->genders[0]->value = 'male'; // male, female, other, unknown

        // @see https://developers.google.com/people/api/rest/v1/people#Person.Address
        if (empty($gcontact->addresses[0])) $gcontact->addresses[0] = new stdClass();
        $gcontact->addresses[0]->type = 'work';
        $gcontact->addresses[0]->streetAddress = $object->address;
        $gcontact->addresses[0]->city = $object->town;
        $gcontact->addresses[0]->region = $object->state;
        $gcontact->addresses[0]->postalCode = $object->zip;
        $gcontact->addresses[0]->country = $object->country;
        $gcontact->addresses[0]->countryCode = $object->country_code;
        if (empty($gcontact->addresses[0]->metadata)) $gcontact->addresses[0]->metadata = new stdClass();
        $gcontact->addresses[0]->metadata->primary = true;

        // @see https://developers.google.com/people/api/rest/v1/people#Person.EmailAddress
        if (empty($gcontact->emailAddresses[0])) $gcontact->emailAddresses[0] = new stdClass();
        $gcontact->emailAddresses[0]->value = $object->email;
        $gcontact->emailAddresses[0]->type = 'work';
        if (empty($gcontact->emailAddresses[0]->metadata)) $gcontact->emailAddresses[0]->metadata = new stdClass();
        $gcontact->emailAddresses[0]->metadata->primary = true;

        // @see https://developers.google.com/people/api/rest/v1/people#Person.PhoneNumber
        if (empty($gcontact->phoneNumbers[0])) $gcontact->phoneNumbers[0] = new stdClass();
        $gcontact->phoneNumbers[0]->value = $object->phone_pro;
        $gcontact->phoneNumbers[0]->type = 'work';
        if (empty($gcontact->phoneNumbers[1])) $gcontact->phoneNumbers[1] = new stdClass();
        $gcontact->phoneNumbers[1]->value = $object->phone_mobile;
        $gcontact->phoneNumbers[1]->type = 'mobile';
        if (empty($gcontact->phoneNumbers[2])) $gcontact->phoneNumbers[2] = new stdClass();
        $gcontact->phoneNumbers[2]->value = $object->phone_perso;
        $gcontact->phoneNumbers[2]->type = 'home';

        // @see https://developers.google.com/people/api/rest/v1/people#Person.Url
        if (empty($gcontact->urls[0])) $gcontact->urls[0] = new stdClass();
        $gcontact->urls[0]->value = dol_buildpath('contact/card.php', 2).'?id='.$object->id;
        $gcontact->urls[0]->type = 'work';
    }

    /**
     * @param RapidWeb\GooglePeopleAPI\Contact $gcontact Object
     * @param User $object Object
     * @return void
     */
    protected function initGContactWithUser(&$gcontact, $object)
    {
        // TODO complete ...
    }

    /**
     * @param RapidWeb\GooglePeopleAPI\Contact $gcontact Object
     * @param User $user Object
     * @return void
     */
    protected function initGContactMemberships(&$gcontact, $user)
    {
        // TODO remove this comment : 7794913e8de20ca6 (membership id )
        if (!empty($user->array_options['options_gsync_group_id']))
        {
            $contactGroupResourceName = 'contactGroups/'.$user->array_options['options_gsync_group_id'];
            // @see https://developers.google.com/people/api/rest/v1/people#Person.Membership
            $found = false;
            if (empty($gcontact->memberships)) $gcontact->memberships = array();

            foreach ($gcontact->memberships as $membership)
            {
                if ($membership->contactGroupMembership->contactGroupResourceName == $contactGroupResourceName)
                {
                    $found = true;
                    break;
                }
            }

            if (!$found)
            {
                $membership = new stdClass();
                $membership->contactGroupMembership = new stdClass();
                $membership->contactGroupMembership->contactGroupResourceName = $contactGroupResourceName;
                array_unshift($gcontact->memberships, $membership);
            }
        }
    }
}

//class GSyncDet extends SeedObject
//{
//    public $table_element = 'gsyncdet';
//
//    public $element = 'gsyncdet';
//
//
//    /**
//     * GSyncDet constructor.
//     * @param DoliDB    $db    Database connector
//     */
//    public function __construct($db)
//    {
//        $this->db = $db;
//
//        $this->init();
//    }
//}
