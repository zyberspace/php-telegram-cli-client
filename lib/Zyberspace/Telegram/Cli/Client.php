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
     * The file handler for the socket-connection
     *
     * @var ressource
     */
    protected $_fp;

    /**
     * Connects to the telegram-cli.
     *
     * @param string $remoteSocket Address of the socket to connect to. See stream_socket_client() for more info.
     *                             Can be 'unix://' or 'tcp://'.
     *
     * @throws ClientException Throws an exception if no connection can be established.
     */
    public function __construct($remoteSocket)
    {
        $this->_fp = stream_socket_client($remoteSocket);
        if ($this->_fp === false) {
            throw new ClientException('Could not connect to socket "' . $remoteSocket . '"');
        }
        stream_set_timeout($this->_fp, 1); //This way fgets() returns false if telegram-cli gives us no response.
    }

    /**
     * Closes the connection to the telegram-cli.
     */
    public function __destruct()
    {
        fclose($this->_fp);
    }

    /**
     * Executes a command on the telegram-cli. Line-breaks will be escaped, as telgram-cli does not support them.
     *
     * @param string $command The command, including all arguments
     *
     * @return boolean|string Returns the answer as string or true on success, false if there was an error.
     */
    public function exec($command)
    {
        fwrite($this->_fp, str_replace("\n", '\n', $command) . PHP_EOL);

        $answer = fgets($this->_fp); //"ANSWER $bytes" if there is a return value or \n if not
        if (is_string($answer)) {
            if (substr($answer, 0, 7) === 'ANSWER ') {
                $bytes = (int) substr($answer, 7);
                if ($bytes > 0) {
                    $string = trim(fread($this->_fp, $bytes + 1));

                    if ($string === 'SUCCESS') { //For "status_online" and "status_offline"
                        return true;
                    }

                    return $string;
                }
            } else if ($answer === PHP_EOL) { //For commands like "msg"
                return true;
            }
        }

        return false;
    }

    /**
     * Escapes strings for usage as command-argument.
     * T"es't -> "T\"es\'t"
     *
     * @param string $argument The argument to escape
     *
     * @return string The escaped command enclosed by double-quotes
     */
    public function escapeStringArgument($argument)
    {
        return '"' . addslashes($argument) . '"';
    }

    /**
     * Replaces all spaces with underscores.
     *
     * @param string $peer The peer to escape
     *
     * @return string The escaped peer
     */
    public function escapePeer($peer)
    {
        return str_replace(' ', '_', $peer);
    }
}
