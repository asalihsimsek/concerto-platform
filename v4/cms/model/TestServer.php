<?php

/*
  Concerto Platform - Online Adaptive Testing Platform
  Copyright (C) 2011-2012, The Psychometrics Centre, Cambridge University

  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; version 2
  of the License, and not any of the later versions.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

class TestServer {

    public static $debug = true;
    public static $debug_stream_data = true;
    public static $sleep_microseconds = 10000;
    private $last_action_time;
    private $main_sock;
    private $clients;
    private $instances;
    private $is_alive = false;

    const SOCK_TYPE_UNIX = 0;
    const SOCK_TYPE_TCP = 1;

    public static function log_debug($message, $code = false) {
        $t = microtime(true);
        $micro = sprintf("%06d", ($t - floor($t)) * 1000000);
        $d = new DateTime(date('Y-m-d H:i:s.' . $micro, $t));
        $datetime = $d->format("Y-m-d H:i:s.u");

        $lfh = fopen(Ini::$path_temp . date('Y-m-d') . ".socket.log", "a");
        fwrite($lfh, ($code ? "\n" : $datetime . " {" . round(memory_get_peak_usage(true) / 1000000, 3) . "MB} --- ") . $message . "\n" . ($code ? "\n" : ""));
        fclose($lfh);
    }

    public function stop() {
        foreach ($this->clients as $k => $v) {
            $this->serialize_instance($k);
        }

        socket_close($this->main_sock);
        if (file_exists(Ini::$path_unix_sock))
            unlink(Ini::$path_unix_sock);
        if (self::$debug)
            self::log_debug("TestServer->stop() --- TestServer stopped");
        $this->is_alive = false;
    }

    public static function send($data) {
        if (self::$debug) {
            self::log_debug("TestServer::send() --- Client sends data");
        }
        $socket = null;
        if (Ini::$server_socks_type == 1)
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (Ini::$server_socks_type == 0)
            $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$socket) {
            if (self::$debug) {
                self::log_debug("TestServer::send() --- Error: (socket_create) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
            }
            return false;
        }
        $result = null;
        if (Ini::$server_socks_type == 1)
            $result = socket_connect($socket, Ini::$server_host, Ini::$server_port);
        if (Ini::$server_socks_type == 0)
            $result = socket_connect($socket, Ini::$path_unix_sock);
        if (!$result) {
            if (self::$debug) {
                self::log_debug("TestServer::send() --- Error: (socket_connect) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
            }

            socket_close($socket);
            return false;
        }
        socket_write($socket, $data . chr(0));
        if (self::$debug) {
            self::log_debug("TestServer::send() --- sent data");
            if (self::$debug_stream_data)
                self::log_debug($data, true);
        }

        $data = "";
        while ($result = socket_read($socket, 4096)) {
            $len = strlen($result);
            $data.=$result;
            if (self::$debug) {
                self::log_debug("TestServer::send() --- data recieved (" . $len . ")");
                if (self::$debug_stream_data)
                    self::log_debug($data, true);
            }
            if (substr($result, -1, 1) == chr(0))
                break;
        }
        if (self::$debug) {
            self::log_debug("TestServer::send() --- reading finished");
        }

        socket_close($socket);
        if ($result === false) {
            if (self::$debug) {
                self::log_debug("TestServer::send() --- Error: (socket_read) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
            }
            return false;
        }
        return trim($data);
    }

    public static function is_running() {
        $socket = null;
        if (Ini::$server_socks_type == 1)
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (Ini::$server_socks_type == 0)
            $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$socket) {
            if (self::$debug) {
                self::log_debug("TestServer::is_running() --- Error: (socket_create) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
            }
            return false;
        }

        $result = null;
        if (Ini::$server_socks_type == 1)
            $result = @socket_connect($socket, Ini::$server_host, Ini::$server_port);
        if (Ini::$server_socks_type == 0)
            $result = @socket_connect($socket, Ini::$path_unix_sock);

        socket_close($socket);
        if (!$result)
            return false;

        if (self::$debug) {
            self::log_debug("TestServer::is_running() --- Server is running");
        }
        return true;
    }

    public static function start_process() {
        if (self::$debug) {
            self::log_debug("TestServer::start_process() --- Starting server process");
        }
        session_write_close();
        $command = 'nohup ' . Ini::$path_php_exe . ' ' . Ini::$path_internal . 'cms/query/socket_start.php ' . Ini::$path_internal . ' >> ' . Ini::$path_temp . date('Y-m-d') . ".php.log" . ' 2>&1 & echo $!';
        exec($command);
        while (!self::is_running()) {
            
        }
        if (self::$debug) {
            self::log_debug("TestServer::start_process() --- Server process started");
        }
        session_start();
    }

    public function start() {
        gc_enable();
        if (file_exists(Ini::$path_unix_sock))
            unlink(Ini::$path_unix_sock);
        $this->last_action_time = time();
        if (self::$debug)
            self::log_debug("TestServer->start() --- TestServer started");
        if (Ini::$server_socks_type == 1)
            $this->main_sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (Ini::$server_socks_type == 0)
            $this->main_sock = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!$this->main_sock) {
            if (self::$debug) {
                self::log_debug("TestServer->start() --- Error: (socket_create) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
                self::log_debug("TestServer->start() --- Server halted!");
            }
            return;
        }

        if (!socket_set_option($this->main_sock, SOL_SOCKET, SO_REUSEADDR, 1)) {
            if (self::$debug) {
                self::log_debug("TestServer->start() --- Error: (socket_set_option) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
                self::log_debug("TestServer->start() --- Server halted!");
            }
            $this->stop();
            return;
        }

        $bind = null;
        if (Ini::$server_socks_type == 1)
            $bind = socket_bind($this->main_sock, Ini::$server_host, Ini::$server_port);
        if (Ini::$server_socks_type == 0)
            $bind = socket_bind($this->main_sock, Ini::$path_unix_sock);
        if (!$bind) {
            if (self::$debug) {
                self::log_debug("TestServer->start() --- Error: (socket_bind) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
                self::log_debug("TestServer->start() --- Server halted!");
            }
            $this->stop();
            return;
        }
        if (!socket_listen($this->main_sock)) {
            if (self::$debug) {
                self::log_debug("TestServer->start() --- Error: (socket_listen) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
                self::log_debug("TestServer->start() --- Server halted!");
            }
            $this->stop();
            return;
        }
        $this->clients = array();
        $this->instances = array();

        if (self::$debug)
            self::log_debug("TestServer->start() --- TestServer initialized");

        if (!socket_set_nonblock($this->main_sock)) {
            if (self::$debug) {
                self::log_debug("TestServer->start() --- Error: (socket_set_nonblock)");
                self::log_debug("TestServer->start() --- Server halted!");
            }
            return;
        }
        $this->is_alive = true;

        do {
            gc_collect_cycles();

            //serialization
            foreach ($this->clients as $k => $v) {
                if ($this->instances[$k]->is_timedout() && !$this->instances[$k]->is_serializing) {
                    if (self::$debug) {
                        self::log_debug("TestServer->start() --- Client '$k' timedout");
                    }
                    $this->serialize_instance($k);
                }
            }

            if (time() - $this->last_action_time > Ini::$r_server_timeout) {
                if (self::$debug)
                    self::log_debug("TestServer->start() --- Reached max idle time");
                break;
            }

            //interpret data start
            foreach ($this->clients as $k => $v) {
                //read
                if ($this->instances[$k]->is_working) {
                    $serialized = false;
                    $response = $this->instances[$k]->read();
                    if ($this->instances[$k]->is_serialized) {
                        $serialized = true;
                    }

                    if ($response != null) {
                        $this->last_action_time = time();

                        $this->instances[$k]->is_data_ready = false;
                        $this->instances[$k]->is_working = false;
                        if (self::$debug) {
                            self::log_debug("TestServer->start() --- Client '$k' test data read");
                            if (self::$debug_stream_data) {
                                self::log_debug($response, true);

                                if ($this->instances[$k]->error_response != "") {
                                    self::log_debug($this->instances[$k]->error_response, true);
                                }
                            }
                        }

                        if (!$serialized) {
                            $response = array(
                                "return" => $this->instances[$k]->code_execution_halted ? 1 : 0
                            );

                            $response = json_encode($response);

                            if (!socket_write($this->clients[$k]["sock"], $response . chr(0))) {
                                if (self::$debug)
                                    self::log_debug("TestServer->start() --- Error: (socket_write) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
                            }
                            else {
                                if (self::$debug) {
                                    self::log_debug("TestServer->start() --- Client '$k' test response sent back");
                                    if (self::$debug_stream_data)
                                        self::log_debug($response, false);
                                }
                            }

                            if ($this->instances[$k]->code_execution_halted)
                                $this->close_instance($k);
                        }
                    }

                    if ($serialized || $this->instances[$k]->is_finished) {
                        $this->last_action_time = time();
                        $this->close_instance($k,$serialized);
                    }
                }
            }
            //interpret data end

            usleep(self::$sleep_microseconds);

            $client_sock = @socket_accept($this->main_sock);
            if (!$client_sock) {
                continue;
            }

            if (self::$debug) {
                self::log_debug("TestServer->start() --- socket accepted");
            }

            $data = "";
            while ($read = socket_read($client_sock, 4096)) {
                $len = strlen($read);
                if (self::$debug) {
                    self::log_debug("TestServer->start() --- socket read (" . $len . ")");
                }
                $data.=$read;
                if (substr($read, -1, 1) == chr(0))
                    break;
            }
            if ($read === false) {
                if (self::$debug) {
                    self::log_debug("TestServer->start() --- Error: (socket_read) " . socket_last_error() . " - " . socket_strerror(socket_last_error()));
                }
                socket_close($client_sock);
                continue;
            }

            $input = trim($data);
            if ($input != "") {
                if (self::$debug) {
                    self::log_debug("TestServer->start() --- data recieved");
                    if (self::$debug_stream_data)
                        self::log_debug($input, true);
                }
                if ($input == "exit") {
                    if (self::$debug)
                        self::log_debug("TestServer->start() --- Exit command recieved");
                    break;
                }
                if (strpos($input, "close:") === 0) {
                    if (self::$debug)
                        self::log_debug("TestServer->start() --- Close command recieved");
                    $vars = explode(":", $input);
                    $this->close_instance("sid" . $vars[1]);
                    continue;
                }
                if (strpos($input, "serialize:") === 0) {
                    if (self::$debug)
                        self::log_debug("TestServer->start() --- Serialize command recieved");
                    $vars = explode(":", $input);
                    $this->serialize_instance("sid" . $vars[1]);
                    continue;
                }
                $this->last_action_time = time();
                $client = $this->get_client($client_sock, $input);
                $this->interpret_input($client, $input);
            }
        }
        while ($this->is_alive);

        $this->stop();
        gc_collect_cycles();
        gc_disable();
    }

    private function close_instance($key, $serialized = false) {
        $session_id = substr($key, 3);
        if (array_key_exists($key, $this->instances)) {
            if ($this->instances[$key]->is_started()) {
                $this->instances[$key]->stop();
                unset($this->instances[$key]);
            }
        }
        if (array_key_exists($key, $this->clients)) {
            socket_close($this->clients[$key]["sock"]);
            unset($this->clients[$key]);
        }
        if ($serialized) {
            $session = TestSession::from_mysql_id($session_id);
            if ($session != null) {
                if ($session->debug == 1)
                    $session->remove();
            }
        }
        if (self::$debug) {
            self::log_debug("TestServer->close_instance() --- Client '$key' closed");
        }
    }

    private function serialize_instance($key) {
        if (array_key_exists($key, $this->instances)) {
            if ($this->instances[$key]->is_started()) {
                $this->instances[$key]->serialize();
            }
        }
        if (self::$debug) {
            self::log_debug("TestServer->serialize_instance() --- Client '$key' is serializing");
        }
    }

    private function get_client($client_sock, &$input) {
        $data = json_decode($input);
        $key = "sid" . $data->session_id;

        if (!array_key_exists($key, $this->clients)) {
            $this->clients[$key] = array();
            $this->clients[$key]["sock"] = $client_sock;
            if (self::$debug) {
                self::log_debug("TestServer->get_client() --- Client '$key' added");
            }
        } else {
            if (is_resource($this->clients[$key]["sock"])) {
                socket_close($this->clients[$key]["sock"]);
                $this->clients[$key]["sock"] = $client_sock;
            }
            if (self::$debug) {
                self::log_debug("TestServer->get_client() --- Client '$key' loaded");
            }
        }
        return $this->clients[$key];
    }

    private function interpret_input($client, $input) {
        $data = json_decode($input);
        $key = "sid" . $data->session_id;

        if (!array_key_exists($key, $this->instances)) {
            $this->instances[$key] = new TestInstance($data->session_id);
            if (self::$debug) {
                self::log_debug("TestServer->interpret_input() --- Client '$key' test instance created");
            }
        }
        if (!$this->instances[$key]->is_started()) {
            $this->instances[$key]->start();
            if (self::$debug) {
                self::log_debug("TestServer->interpret_input() --- Client '$key' test instance started");
            }
        }
        $this->instances[$key]->run($data->code, $data->values);
        if (self::$debug) {
            self::log_debug("TestServer->interpret_input() --- Client '$key' test data sent");
            if (self::$debug_stream_data)
                self::log_debug($data->code, true);
        }
    }

}

?>
