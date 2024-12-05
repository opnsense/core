<?php

/*
 * Copyright (C) 2023 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Core;

class FileObject
{
    private $fhandle = null;

    /**
     * File object wrapper
     * @param string $filename Path to the file where to write the data.
     * @param string $mode type of access you require to the stream.
     * @param int $permissions permissions to set, see chmod for usage
     * @param bool $operation flock operation mode when set
     */
    public function __construct($filename, $mode, $permissions = null, $operation = null)
    {
        $this->fhandle = fopen($filename, $mode . 'e');   /* always add close-on-exec flag to prevent fork inherit */

        if ($permissions != null) {
            @chmod($filename, $permissions);
        }
        if ($operation != null) {
            if (!flock($this->fhandle, $operation)) {
                fclose($this->fhandle);
                $this->fhandle = null;
                throw new Exception('Unable to open file in requested mode.');
            }
        }
    }

    /**
     * close and unlock filehandle
     */
    function __destruct()
    {
        if ($this->fhandle) {
            fclose($this->fhandle);
        }
    }

    /**
     * Unlock when locked
     * @return this
     */
    public function unlock()
    {
        flock($this->fhandle, LOCK_UN);
        return $this;
    }

    /**
     * seek
     * @param int $offset offset to use
     * @param int $whence start position
     * @return this
     */
    public function seek(int $offset, int $whence = SEEK_SET)
    {
        fseek($this->fhandle, $whence);
        return $this;
    }

    /**
     * truncate this file
     * @param int $whence start position
     * @return this
     */
    public function truncate(int $size)
    {
        ftruncate($this->fhandle, $size);
        return $this;
    }

    /**
     * read this file
     * @param int $whence start position
     * @return payload
     */
    public function read(int $length = -1)
    {
        if ($length == -1) {
            $length = fstat($this->fhandle)['size'];
        }
        if ($length === 0) {
            return '';
        }
        return fread($this->fhandle, $length);
    }

    /**
     * write contents to this file
     * @param string $data start position
     * @param int $length length to write
     * @param bool $sync flush data
     * @return this
     */
    public function write(string $data, ?int $length = null, bool $sync = true)
    {
        fwrite($this->fhandle, $data, $length);
        if ($sync) {
            fflush($this->fhandle);
        }
        return $this;
    }

    /**
     * read and parse json content
     * @return array
     */
    public function readJson()
    {
        return json_decode($this->read(), true);
    }

    /**
     * write array as json data
     * @return this
     */
    public function writeJson(array $data)
    {
        return $this->write(json_encode($data));
    }
}
