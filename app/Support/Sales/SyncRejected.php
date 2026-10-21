<?php

namespace App\Support\Sales;

use RuntimeException;

/** An offline op that can never be applied (the device shows it as a sync problem). */
class SyncRejected extends RuntimeException {}
