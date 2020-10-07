# PHP Reverse Shell

Just a little refresh on the popular PHP reverse shell script [github.com/pentestmonkey/php-reverse-shell](https://github.com/pentestmonkey/php-reverse-shell). Credits to the original author!

Works on Linux OS and macOS with `/bin/sh` and Windows OS with `cmd.exe`. Script will automatically detect an underlying OS.

Works with both `ncat` and `multi/handler`.

Tested on XAMPP for Linux v7.3.19 (64-bit) with PHP v7.3.19 on Kali Linux v2020.2 (64-bit).

Tested on XAMPP for Windows v7.4.3 (64-bit) with PHP v7.4.3 on Windows 10 Enterprise OS (64-bit).

Tested on XAMPP for OS X v7.4.10 (64-bit) with PHP v7.4.10 on macOS Catalina v10.15.6 (64-bit).

Made for educational purposes. I hope it will help!

P.S. Process pipes on Windows OS do not support asynchronous operations so `stream_set_blocking()`, `stream_select()` and `feof()` will not work properly but I found a workaround.

## How to Run

[/src/php_reverse_shell.php](https://github.com/ivan-sincek/php-reverse-shell/blob/master/src/php_reverse_shell.php) requires PHP v5.0.0 or greater, mainly because `proc_get_status()` is being used.

[/src/php_reverse_shell_older.php](https://github.com/ivan-sincek/php-reverse-shell/blob/master/src/php_reverse_shell_older.php) requires PHP v4.3.0 or greater.

**Change the IP address and port number inside the script as necessary.**

Copy [/src/php_reverse_shell.php](https://github.com/ivan-sincek/php-reverse-shell/blob/master/src/php_reverse_shell.php) to your server's web root directory (e.g. to /opt/lampp/htdocs/ on XAMPP) or upload it to your target's web server.

To set up a listener, open the GNOME Terminal and run the following Bash command:

```fundamental
ncat -nvlp 9000
```

Navigate to the file with your preferred web browser.

## Images

<p align="center"><img src="https://github.com/ivan-sincek/php-reverse-shell/blob/master/img/ncat.jpg" alt="Ncat"></p>

<p align="center">Figure 1 - Ncat</p>

<p align="center"><img src="https://github.com/ivan-sincek/php-reverse-shell/blob/master/img/scripts_dump.jpg" alt="Script Dump"></p>

<p align="center">Figure 2 - Script's Dump</p>
