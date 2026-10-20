<?php

namespace SergeyBruhin\NovaSupportRays\Exceptions;

use RuntimeException;

/** SupportRays couldn't be reached at all: a timeout or a refused connection. */
class SupportRaysUnavailable extends RuntimeException {}
