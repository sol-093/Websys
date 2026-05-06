<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - WEB ENTRY POINT
 * ================================================
 *
 * SECTION MAP:
 * 1. Bootstrap Runtime
 * 2. Dispatch Actions
 * 3. Dispatch Pages
 *
 * WORK GUIDE:
 * - Change this file only when the top-level request pipeline changes.
 * - Add new routes in includes/routes/actions.php or includes/routes/pages.php.
 * ================================================
 */

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/routes/actions.php';
require __DIR__ . '/includes/routes/pages.php';
