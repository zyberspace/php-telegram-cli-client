<?php
/**
 * Copyright 2015 Eric Enold <zyberspace@zyberware.org>
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Zyberspace\Telegram\Cli;

/**
 * php-client for telegram-cli.
 * If you don't need the command-wrappers in this class or want to make your own, use the RawClient-class. :)
 */
class Client extends RawClient
{
    /**
     * Sets status as online.
     *
     * @return boolean true on success, false otherwise
     *
     * @uses exec()
     */
    public function setStatusOnline()
    {
        return $this->exec('status_online');
    }

    /**
     * Sets status as offline.
     *
     * @return boolean true on success, false otherwise
     *
     * @uses exec()
     */
    public function setStatusOffline()
    {
        return $this->exec('status_offline');
    }

    /**
     * Sends a typing notification to $peer.
     * Lasts a couple of seconds or till you send a message (whatever happens first).
     *
     * @param string $peer The peer, gets escaped with escapePeer()
     *
     * @return boolean true on success, false otherwise
     */
    public function sendTyping($peer)
    {
        return $this->exec('send_typing ' . $this->escapePeer($peer));
    }

    /**
     * Sends a text message to $peer.
     *
     * @param string $peer The peer, gets escaped with escapePeer()
     * @param string $msg The message to send, gets escaped with escapeStringArgument()
     *
     * @return boolean true on success, false otherwise
     *
     * @uses exec()
     * @uses escapePeer()
     * @uses escapeStringArgument()
     */
    public function msg($peer, $msg)
    {
        $peer = $this->escapePeer($peer);
        $msg = $this->escapeStringArgument($msg);
        return $this->exec('msg ' . $peer . ' ' . $msg);
    }

    /**
     * Sends a text message to several users at once.
     *
     * @param array $userList List of users / contacts that shall receive the message,
     *                        gets formated with formatPeerList()
     * @param string $msg The message to send, gets escaped with escapeStringArgument()
     *
     * @return boolean true on success, false otherwise
     */
    public function broadcast(array $userList, $msg)
    {
        return $this->exec('broadcast ' . $this->formatPeerList($userList) . ' '
            . $this->escapeStringArgument($msg));
    }

    /**
     * Sets the profile name
     *
     * @param $firstName The first name
     * @param $lastName The last name
     *
     * @return object|boolean Your new user-info as an object; false if somethings goes wrong
     *
     * @uses exec()
     */
    public function setProfileName($firstName, $lastName)
    {
        return $this->exec('set_profile_name ' . $this->escapeStringArgument($firstName) . ' '
            . $this->escapeStringArgument($lastName));
    }

    /**
     * Adds a user to the contact list
     *
     * @param string $phoneNumber The phone-number of the new contact, needs to be a telegram-user.
     *                            Every char that is not a number gets deleted, so you don't need to care about spaces,
     *                            '+' and so on.
     * @param string $firstName The first name of the new contact
     * @param string $lastName The last name of the new contact
     *
     * @return object|boolean The new contact-info as an object; false if somethings goes wrong
     *
     * @uses exec()
     * @uses escapeStringArgument()
     */
    public function addContact($phoneNumber, $firstName, $lastName)
    {
        $phoneNumber = preg_replace('%[^0-9]%', '', (string) $phoneNumber);
        if (empty($phoneNumber)) {
            return false;
        }

        return $this->exec('add_contact ' . $phoneNumber . ' ' . $this->escapeStringArgument($firstName)
            . ' ' . $this->escapeStringArgument($lastName));
    }

    /**
     * Renames a user in the contact list
     *
     * @param string $contact The contact, gets escaped with escapePeer()
     * @param string $firstName The new first name for the contact
     * @param string $lastName The new last name for the contact
     *
     * @return object|boolean The new contact-info as an object; false if somethings goes wrong
     *
     * @uses exec()
     * @uses escapeStringArgument()
     */
    public function renameContact($contact, $firstName, $lastName)
    {
        return $this->exec('rename_contact ' . $this->escapePeer($contact)
            . ' ' . $this->escapeStringArgument($firstName) . ' ' . $this->escapeStringArgument($lastName));
    }

    /**
     * Deletes a contact.
     *
     * @param string $contact The contact, gets escaped with escapePeer()
     *
     * @return boolean true on success, false otherwise
     *
     * @uses exec()
     * @uses escapePeer()
     */
    public function deleteContact($contact)
    {
        return $this->exec('del_contact ' . $this->escapePeer($contact));
    }


    /**
     * Blocks a user .
     *
     * @param string $user The user, gets escaped with escapePeer()
     *
     * @return boolean true on success, false otherwise
     *
     * @uses exec()
     * @uses escapePeer()
     */
    public function blockUser($user)
    {
        return $this->exec('block_user ' . $this->escapePeer($user));
    }

    /**
     * Unblocks a user.
     *
     * @param string $user The user, gets escaped with escapePeer()
     *
     * @return boolean true on success, false otherwise
     *
     * @uses exec()
     * @uses escapePeer()
     */
    public function unblockUser($user)
    {
        return $this->exec('unblock_user ' . $this->escapePeer($user));
    }

    /**
     * Marks all messages with $peer as read.
     *
     * @param string $peer The peer, gets escaped with escapePeer()
     *
     * @return boolean true on success, false otherwise
     *
     * @uses exec()
     * @uses escapePeer()
     */
    public function markRead($peer)
    {
        return $this->exec('mark_read ' . $this->escapePeer($peer));
    }

    /**
     * Returns an array of all contacts. Every contact is an object like it gets returned from `getUserInfo()`.
     *
     * @return array|boolean An array with your contacts as objects; false if somethings goes wrong
     *
     * @uses exec()
     *
     * @see getUserInfo()
     */
    public function getContactList()
    {
        return $this->exec('contact_list');
    }

    /**
     * Returns the informations about the user as an object.
     *
     * @param string $user The user, gets escaped with escapePeer()
     *
     * @return object|boolean An object with informations about the user; false if somethings goes wrong
     *
     * @uses exec()
     * @uses escapePeer()
     */
    public function getUserInfo($user)
    {
        return $this->exec('user_info ' . $this->escapePeer($user));
    }

    /**
     * Returns an array of all your dialogs. Every dialog is an object with type "user" or "chat".
     *
     * @return array|boolean An array with your dialogs; false if somethings goes wrong
     *
     * @uses exec()
     *
     * @see getUserInfo()
     */
    public function getDialogList()
    {
        return $this->exec('dialog_list');
    }

    /**
     * Returns an array of your past message with that $peer. Every message is an object which provides informations
     * about it's type, sender, retriever and so one.
     * All messages will also be marked as read.
     *
     * @param string $peer The peer, gets escaped with escapePeer()
     * @param int $limit (optional) Limit answer to $limit messages. If not set, there is no limit.
     * @param int $offset (optional) Use this with the $limit parameter to go through older messages.
     *                    Can also be negative.
     *
     * @return array|boolean An array with your past messages with that $peer; false if somethings goes wrong
     *
     * @uses exec()
     * @uses escapePeer()
     */
    public function getHistory($peer, $limit = null, $offset = null)
    {
        if ($limit !== null) {
            $limit = (int) $limit;
            if ($limit < 1) { //if limit is lesser than 1, telegram-cli crashes
                $limit = 1;
            }
            $limit = ' ' . $limit;
        } else {
            $limit = '';
        }
        if ($offset !== null) {
            $offset = ' ' . (int) $offset;
        } else {
            $offset = '';
        }

        return $this->exec('history ' . $this->escapePeer($peer) . $limit . $offset);
    }
}
