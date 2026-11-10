<?php

namespace App\Services\Import;

use RuntimeException;

/** Thrown inside the import transaction to undo everything after a dry run. */
class DryRunRollback extends RuntimeException {}
