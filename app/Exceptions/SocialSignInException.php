<?php

namespace App\Exceptions;

use RuntimeException;

/** A sign-in with an outside account that cannot go ahead; the message is shown to the user. */
class SocialSignInException extends RuntimeException {}
