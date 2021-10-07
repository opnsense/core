<?php

/**
 * Polyfill for PHP < 5.4
 */
if (!interface_exists('JsonSerializable', false)) {
    interface JsonSerializable
    {
        /**
         * @param void
         * @return mixed
         */
        function jsonSerialize();
    }
}
