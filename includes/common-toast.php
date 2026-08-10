<?php
declare(strict_types=1);

/**
 * Backward-compatibility wrapper.
 * Old pages that still require includes/common-toast.php will use the same
 * single global toast implementation instead of loading a second toast system.
 */
require_once __DIR__ . '/toast.php';
