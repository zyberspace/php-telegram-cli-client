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
 * php-client for telegram-cli
 */
class Client extends AbstractClientCommands
{

    /**
     * Sends a broadcast $msg to the array of $peers supplied
     *
     * @param array  $peers
     * @param string $msg
     * @return bool|string
     */
    public function broadcast(array $peers, $msg)
    {
        $peerList = $this->formatPeers($peers);
        $msg      = $this->escapeStringArgument($msg);

        return $this->exec('broadcast ' . $peerList . ' ' . $msg);
    }


    /**
     * Add a $peer to a group chat. Sends him the last $msgToForward messages from this chat
     *
     * @param        $chat
     * @param string $peer
     * @param int    $msgToForward
     * @return mixed
     */
    public function chatAddUser($chat, $peer, $msgToForward = 100)
    {
        $chat = $this->escapePeer($chat); //Not escapeStringArgument as chat needs underscores if spaces in name
        $peer = $this->escapePeer($peer);

        return $this->exec('chat_add_user ' . $chat . ' ' . $peer . ' ' . $msgToForward);
    }

    /**
     * Create a group chat with $peers
     *
     * @param string $groupName The name of the group chat
     * @param array  $peers     All peers to be included in chat
     * @return bool|string
     */
    public function chatCreateGroup($groupName, array $peers)
    {
        $groupName = $this->escapeStringArgument($groupName);
        $peerList  = $this->formatPeers($peers);

        return $this->exec('create_group_chat ' . $groupName . ' ' . $peerList);
    }

    /**
     * Create a secret chat with $peer
     *
     * @param string $peer The peer you wish to start a secret chat with.
     * @return bool|string
     */
    public function chatCreateSecret($peer)
    {
        $peer = $this->escapePeer($peer);

        return $this->exec('create_secret_chat ' . $peer);
    }

    /**
     * Formats an array of peers so they can be used with group chats
     *
     * @param array $peers
     * @return string
     */
    private function formatPeers(array $peers)
    {
        $peers = array_map(array($this, 'escapePeer'), $peers);

        return implode(' ', $peers);
    }

    /**
     * Returns info about a chat (id, members, admin, etc.)
     *
     * @param $chat
     * @return bool|string
     */
    public function chatInfo($chat){
        $chat = $this->escapePeer($chat);

        return $this->exec('chat_info '. $chat);
    }

    /**
     * Deletes $peer from $chat
     *
     * @param        $chat
     * @param string $peer
     * @return mixed
     */
    public function chatDelUser($chat, $peer)
    {
        $chat = $this->escapePeer($chat); //Not escapeStringArgument as chat needs underscores if spaces in name
        $peer = $this->escapePeer($peer);

        return $this->exec('chat_del_user ' . $chat . ' ' . $peer);
    }

    /**
     * Sets the group chat picture for specified $chat
     *
     * The photo will be cropped to square
     *
     * @param        $chat
     * @param string $mediaUri Either a URL or a local filename of the photo you wish to set
     * @return bool|string
     * @uses     exec()
     */
    public function chatSetPhoto($chat, $mediaUri)
    {
        $chat = $this->escapePeer($chat); //Not escapeStringArgument as chat needs underscores if spaces in name

        //Process the requested media file.
        $processedMedia = $this->processMediaUri($mediaUri);
        if ( ! $processedMedia) {
            return false;
        }

        //Send media file.
        $result = $this->exec('chat_set_photo ' . $chat . ' ' . $processedMedia['filepath']);

        //Clean up if media file came from REMOTE address
        $this->cleanUpMedia($processedMedia);

        return $result;
    }

    /**
     * Takes a URI (in the form of a URL or local file path) and determines if
     * the file exists and that it is not too big. If the file is remote (ie a URL)
     * it will download the media file to the system temp directory for use.
     *
     * @param string $fileUri
     * @param int    $maxsizebytes
     * @return array|bool
     */
    protected function processMediaUri($fileUri, $maxsizebytes = 10485760)
    {
        //Setup the mediafile Array to contain all the file's info.
        $mediaFileInfo = array();

        if (filter_var($fileUri, FILTER_VALIDATE_URL) !== false) {
            //The URI provided was a URL. Lets check to see if it exists.
            $mediaFileInfo = $this->checkUrlExistsAndSize($fileUri, $mediaFileInfo);

            if ( ! $mediaFileInfo || $mediaFileInfo['filesize'] > $maxsizebytes) {
                //File too big. Or doesn't exist. Don't Download.
                return false;
            }

            //Lets see if we can use the file name given to us, otherwise we'll create a new unique filename.
            $originalFilename = pathinfo($fileUri, PATHINFO_BASENAME);
            $mediaFileInfo    = $this->determineFilename($originalFilename, $mediaFileInfo);

            $tempFileName = fopen($mediaFileInfo['filepath'], 'w');
            if ($tempFileName) {
                $this->downloadMediaFileFromURL($fileUri, $tempFileName);
                fclose($tempFileName);
            } else {
                unlink($mediaFileInfo['filepath']);

                return false;
            }

            //Success! We now have the file locally on our system to use.
            return $mediaFileInfo;

        } else {
            if (is_file($fileUri)) {
                //URI given was a local file name.
                $mediaFileInfo['filesize'] = filesize($fileUri);
                if ($mediaFileInfo['filesize'] > $maxsizebytes) {
                    //File too big
                    return false;
                }
                $mediaFileInfo['filepath']      = $fileUri;
                $mediaFileInfo['fileextension'] = pathinfo($fileUri, PATHINFO_EXTENSION);

//                $mediaFileInfo['filemimetype']  = get_mime($filepath);

                return $mediaFileInfo;
            }
        }

        //Couldn't tell what file was, local or URL.
        return false;
    }

    /**
     * Check that the URL given actually exists and is resolvable and that
     * the file located there is within size limits.
     *
     * What are the size limits? I dunno!
     *
     * @param string $fileUri
     * @param array  $mediaFileInfo
     * @return bool|array
     */
    protected function checkUrlExistsAndSize($fileUri, array $mediaFileInfo)
    {
        $mediaFileInfo['url'] = $fileUri;
        //File is a URL. Create a curl connection but DON'T download the body content
        //because we want to see if file is too big.
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "$fileUri");
        curl_setopt($curl, CURLOPT_USERAGENT,
            "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.11) Gecko/20071127 Firefox/2.0.0.11");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_NOBODY, true);

        if (curl_exec($curl) === false) {
            return false;
        }

        //While we're here, get mime type and filesize and extension
        $info                           = curl_getinfo($curl);
        $mediaFileInfo['filesize']      = $info['download_content_length'];
        $mediaFileInfo['filemimetype']  = $info['content_type'];
        $mediaFileInfo['fileextension'] = pathinfo(parse_url($mediaFileInfo['url'], PHP_URL_PATH), PATHINFO_EXTENSION);
        curl_close($curl);

        return $mediaFileInfo;
    }

    /**
     * Determine if we can use the filename given to us via a URI or do
     * we have to create an unique one in the system folder.
     *
     * @param string $originalFilename
     * @param array  $mediaFileInfo
     * @return mixed
     */
    protected function determineFilename($originalFilename, array $mediaFileInfo)
    {
        if (is_null($originalFilename) || ! isset($originalFilename) || is_file(sys_get_temp_dir() . '/' . $originalFilename)) {
            //Need to create a unique file name as file either exists or we couldn't determine it.
            //Create temp file in system folder.
            $uniqueFilename = tempnam(sys_get_temp_dir(), 'tg');
            //Add file extension
            rename($uniqueFilename, $uniqueFilename . '.' . $mediaFileInfo['fileextension']);

            $mediaFileInfo['filepath'] = $uniqueFilename . '.' . $mediaFileInfo['fileextension'];

        } else {
            $mediaFileInfo['filepath'] = sys_get_temp_dir() . '/' . $originalFilename;
        }

        return $mediaFileInfo;
    }

    /**
     * Download the file from the URL provided.
     *
     * @param string $fileUri
     * @param string $tempFileName
     */
    protected function downloadMediaFileFromURL($fileUri, $tempFileName)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "$fileUri");
        curl_setopt($curl, CURLOPT_USERAGENT,
            "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.11) Gecko/20071127 Firefox/2.0.0.11");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_NOBODY, false);
        curl_setopt($curl, CURLOPT_BUFFERSIZE, 1024);
        curl_setopt($curl, CURLOPT_FILE, $tempFileName);
        curl_exec($curl);
        curl_close($curl);
    }

    /**
     * Clean up any temp files created if media file came from REMOTE address (eg URL)
     * @param array $processedMedia
     */
    protected function cleanUpMedia(array $processedMedia)
    {
        if (isset($processedMedia['url']) && file_exists($processedMedia['filepath'])) {
            unlink($processedMedia['filepath']);
        }
    }

    /**
     * Adds a user to the contact list
     *
     * @param int|string $phoneNumber The phone-number of the new contact, needs to be a telegram-user.
     *                                Can start with or without '+'.
     * @param string     $firstName   The first name of the new contact
     * @param string     $lastName    The last name of the new contact
     *
     * @return string|boolean The new contact "$firstName $lastName"; false if somethings goes wrong
     *
     * @uses exec()
     * @uses escapeStringArgument()
     */
    public function contactAdd($phoneNumber, $firstName, $lastName)
    {
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);

        return $this->exec('add_contact ' . $phoneNumber . ' ' . $this->escapeStringArgument($firstName)
            . ' ' . $this->escapeStringArgument($lastName));
    }

    /**
     * @param $phoneNumber
     * @return int|string
     */
    protected function formatPhoneNumber($phoneNumber)
    {
        $phoneNumber = preg_replace("/[^0-9]/", "", $phoneNumber);

        if ($phoneNumber[0] != 0) {
            return ('+' . $phoneNumber);
        }

        return $phoneNumber;
    }


    /**
     * Deletes a contact.
     *
     * @param string $contact The contact, gets escaped with escapePeer(),
     *                        so you can directly use the values from getContactList()
     *
     * @return boolean true on success, false otherwise
     *
     * @uses exec()
     * @uses escapePeer()
     */
    public function contactDelete($contact)
    {
        return $this->exec('del_contact ' . $this->escapePeer($contact));
    }

    /**
     * Renames a user in the contact list
     *
     * @param string $contact   The contact, gets escaped with escapePeer(),
     *                          so you can directly use the values from getContactList()
     * @param string $firstName The new first name for the contact
     * @param string $lastName  The new last name for the contact
     *
     * @return string|boolean The renamed contact "$firstName $lastName"; false if somethings goes wrong
     *
     * @uses exec()
     * @uses escapeStringArgument()
     */
    public function contactRename($contact, $firstName, $lastName)
    {
        $contact   = $this->escapePeer($contact);
        $firstName = $this->escapeStringArgument($firstName);
        $lastName  = $this->escapeStringArgument($lastName);

        return $this->exec('rename_contact ' . $contact . ' ' . $firstName . ' ' . $lastName);
    }

    /**
     * Returns an array of all contacts in form of "[firstName] [lastName]".
     *
     * @return array|boolean An array with your contacts; false if somethings goes wrong
     *
     * @uses exec()
     */
    public function getContactList()
    {
        return explode(PHP_EOL, $this->exec('contact_list'));
    }

    /**
     * Returns an array of all your dialogs in form of
     * "User [firstName] [lastName]: [number of unread messages] unread"  Or
     * "Chat [group chat name]: [number of unread messages] unread"
     *
     * Will get better formatted in the future.
     *
     * @return array|boolean An array with your dialogs; false if somethings goes wrong
     *
     * @uses exec()
     */
    public function getDialogList()
    {
        return explode(PHP_EOL, $this->exec('dialog_list'));
    }

    /**
     * Executes the history-command and returns the answer (the answer is un-formatted right now).
     * Will get better formatted in the future.
     *
     * @param string $peer   The peer, gets escaped with escapePeer(),
     *                       so you can directly use the values from getContactList()
     * @param int    $limit  (optional) Limit answer to $limit messages. If not set, there is no limit.
     * @param int    $offset (optional) Use this with the $limit parameter to go through older messages.
     *                       Can also be negative.
     *
     * @return string|boolean The answer of the history-command; false if somethings goes wrong
     *
     * @uses exec()
     * @uses escapePeer()
     *
     * @see  https://core.telegram.org/method/messages.getHistory
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

    /**
     * Executes the user_info-command and returns it answer (the answer is unformated right now).
     * Will get better formated in the future.
     *
     * @param string $user The user, gets escaped with escapePeer(),
     *                     so you can directly use the values from getContactList()
     *
     * @return string|boolean The answer of the user_info-command; false if somethings goes wrong
     *
     * @uses exec()
     * @uses escapePeer()
     */
    public function getUserInfo($user)
    {
        return $this->exec('user_info ' . $this->escapePeer($user));
    }

    /**
     * Marks all messages with $peer as read.
     *
     * @param string $peer The peer, gets escaped with escapePeer(),
     *                     so you can directly use the values from getContactList()
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
     * Alias function for sendMsg
     * @param string $peer
     * @param string $msg
     * @return bool
     */
    public function msg($peer, $msg)
    {
        return $this->sendMsg($peer, $msg);
    }

    /**
     * Sends a text message to $peer.
     *
     * @param string $peer The peer, gets escaped with escapePeer(),
     *                     so you can directly use the values from getContactList()
     * @param string $msg  The message to send, gets escaped with escapeStringArgument()
     *
     * @return boolean true on success, false otherwise
     *
     * @uses exec()
     * @uses escapePeer()
     * @uses escapeStringArgument()
     */
    public function sendMsg($peer, $msg)
    {
        $peer = $this->escapePeer($peer);
        $msg  = $this->escapeStringArgument($msg);

        return $this->exec('msg ' . $peer . ' ' . $msg);
    }

    /**
     * Sends a Audio file to $peer
     *
     * @param string $peer
     * @param string $mediaUri Either a URL or a local filename of the audio you wish to send
     *
     * @uses exec()
     * @uses escapePeer()
     * @uses escapeStringArgument()
     * @return bool
     */
    public function sendAudio($peer, $mediaUri)
    {
        $peer = $this->escapePeer($peer);

        //Process the requested media file.
        $processedMedia = $this->processMediaUri($mediaUri);
        if ( ! $processedMedia) {
            return false;
        }

        //Send media file.
        $result = $this->exec('send_audio ' . $peer . ' ' . $processedMedia['filepath']);

        //Clean up if media file came from REMOTE address
        $this->cleanUpMedia($processedMedia);

        return $result;
    }

    /**
     * Sends contact to $peer (not necessary telegram user)
     * @param string $peer
     * @param string $phoneNumber in format
     * @param string $firstName
     * @param string $lastName
     * @return mixed
     */
    public function sendContact($peer, $phoneNumber, $firstName, $lastName)
    {
        $peer        = $this->escapePeer($peer);
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        $firstName   = $this->escapeStringArgument($firstName);
        $lastName    = $this->escapeStringArgument($lastName);

        return $this->exec('send_contact  ' . $peer . ' ' . $phoneNumber . ' ' . $firstName . ' ' . $lastName);
    }

    /**
     * Sends a Document to $peer
     *
     * @param string $peer
     * @param string $mediaUri Either a URL or a local filename of the media you wish to send
     * @uses exec()
     * @uses escapePeer()
     * @uses escapeStringArgument()
     * @return bool
     */
    public function sendDocument($peer, $mediaUri)
    {
        $peer = $this->escapePeer($peer);

        //Process the requested media file.
        $processedMedia = $this->processMediaUri($mediaUri);
        if ( ! $processedMedia) {
            return false;
        }

        //Send media file.
        $result = $this->exec('send_document ' . $peer . ' ' . $processedMedia['filepath']);

        //Clean up if media file came from REMOTE address
        $this->cleanUpMedia($processedMedia);

        return $result;
    }

    /**
     * Sends a map of the supplied lat/long coordinated to $peer
     *
     * @param string $peer
     * @param string $latitude  in decimal format up to 6 decimal places. Eg: 37.018757
     * @param string $longitude in decimal format up to 6 decimal places. Eg: -7.965297
     *
     * @uses exec()
     * @uses escapePeer()
     *
     * @return mixed
     */
    public function sendLocation($peer, $latitude, $longitude)
    {
        $latitude  = $this->formatCoordinate($latitude);
        $longitude = $this->formatCoordinate($longitude);
        $peer      = $this->escapePeer($peer);

        return $this->exec('send_location  ' . $peer . ' ' . $latitude . ' ' . $longitude);
    }

    /**
     * Formats the coordinates given to a max of 6 decimal places.
     * @param $coordinate
     * @return float
     */
    protected function formatCoordinate($coordinate)
    {
        return (float) round(preg_replace("/[^0-9.-]/", "", $coordinate), 6);
    }

    /**
     * Sends a Photo to $peer
     *
     * @param string $peer
     * @param string $mediaUri Either a URL or a local filename of the image you wish to send
     *
     * @uses exec()
     * @uses escapePeer()
     * @uses escapeStringArgument()
     * @return bool
     */
    public function sendPhoto($peer, $mediaUri)
    {
        $peer = $this->escapePeer($peer);

        //Process the requested media file.
        $processedMedia = $this->processMediaUri($mediaUri);
        if ( ! $processedMedia) {
            return false;
        }

        //Send media file.
        $result = $this->exec('send_photo ' . $peer . ' ' . $processedMedia['filepath']);

        //Clean up if media file came from REMOTE address
        $this->cleanUpMedia($processedMedia);

        return $result;
    }

    /**
     * Sends the **contents** of a text file to $peer as plain text message
     *
     * @param string $peer
     * @param string $mediaUri Either a URL or a local filename of the text file you wish to send
     *
     * @uses exec()
     * @uses escapePeer()
     * @uses escapeStringArgument()
     * @return bool
     */
    public function sendText($peer, $mediaUri)
    {
        $peer = $this->escapePeer($peer);

        //Process the requested media file.
        $processedMedia = $this->processMediaUri($mediaUri);
        if ( ! $processedMedia) {
            return false;
        }

        //Send media file.
        $result = $this->exec('send_text ' . $peer . ' ' . $processedMedia['filepath']);

        //Clean up if media file came from REMOTE address
        $this->cleanUpMedia($processedMedia);

        return $result;
    }

    /**
     * Send the typing status to $peer
     *
     * @param string $peer
     *
     * @uses escapePeer()
     * @return mixed
     */
    public function sendTypingStart($peer)
    {
        $peer = $this->escapePeer($peer);

        return $this->exec('send_typing ' . $peer);
    }

    /**
     * Stop the typing status to $peer
     *
     * @param string $peer
     *
     * @uses escapePeer()
     * @return mixed
     */
    public function sendTypingStop($peer)
    {
        $peer = $this->escapePeer($peer);

        return $this->exec('send_typing_abort ' . $peer);
    }

    /**
     * Sends a Video to $peer
     *
     * @param string $peer
     * @param string $mediaUri Either a URL or a local filename of the video you wish to send
     *
     * @uses exec()
     * @uses escapePeer()
     * @uses escapeStringArgument()
     * @return bool
     */
    public function sendVideo($peer, $mediaUri)
    {
        $peer = $this->escapePeer($peer);

        //Process the requested media file.
        $processedMedia = $this->processMediaUri($mediaUri);
        if ( ! $processedMedia) {
            return false;
        }

        //Send media file.
        $result = $this->exec('send_video ' . $peer . ' ' . $processedMedia['filepath']);

        //Clean up if media file came from REMOTE address
        $this->cleanUpMedia($processedMedia);

        return $result;
    }

    /**
     * Sets the logged in users profile name
     *
     * @param string $firstName The new first name for the profile
     * @param string $lastName  The new last name for the profile
     *
     * @uses exec()
     * @uses escapeStringArgument()
     * @return string|boolean
     */
    public function setProfileName($firstName, $lastName)
    {
        return $this->exec('set_profile_name ' . $this->escapeStringArgument($firstName) . ' ' . $this->escapeStringArgument($lastName));
    }

    /**
     * Sets the profile picture for the logged in user
     *
     * The photo will be cropped to square
     *
     * @param string $mediaUri Either a URL or a local filename of the photo you wish to set
     *
     * @uses     exec()
     * @return bool|string
     */
    public function setProfilePhoto($mediaUri)
    {
        //Process the requested media file.
        $processedMedia = $this->processMediaUri($mediaUri);
        if ( ! $processedMedia) {
            return false;
        }

        //Send media file.
        $result = $this->exec('set_profile_photo ' . $processedMedia['filepath']);

        //Clean up if media file came from REMOTE address
        $this->cleanUpMedia($processedMedia);

        return $result;
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
     * Sets the username for the logged in User
     * Must be a minimum of 5 characters.
     *
     * @param string $username
     * @return mixed
     */
    public function setUsername($username)
    {
        return $this->exec('set_username ' . $username);
    }
}