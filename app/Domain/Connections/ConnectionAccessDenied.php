<?php

namespace App\Domain\Connections;

/** A credential was requested outside the connection's workspace, grant or state. */
class ConnectionAccessDenied extends \RuntimeException {}
