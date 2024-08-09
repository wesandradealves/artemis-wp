<?php

defined('ABSPATH') or die("");
interface DUP_PRO_iChecker
{
    /**
     * Check if the environment is valid
     *
     * @return bool
     */
    public function check();
    /**
     * Get the value of errors
     *
     * @return mixed[]
     */
    public function getErrors();
    /**
     * Get the value of helper_messages
     *
     * @return mixed[]
     */
    public function getHelperMessages();
}
