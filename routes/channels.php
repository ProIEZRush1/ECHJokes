<?php

use Illuminate\Support\Facades\Broadcast;

// Public channel for joke call status updates (no auth needed since session_id is unguessable ULID)
// Using public channel avoids the complexity of guest broadcast auth
