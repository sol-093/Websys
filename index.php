<?php

declare(strict_types=1);

/*
 * ================================================
 * INVOLVE - WEB ENTRY POINT
 * ================================================
 *
 * TABLE OF CONTENTS:
 * 1. Bootstrap Runtime
 * 2. Dispatch Actions
 * 3. Dispatch Pages
 *
 * EDIT GUIDE:
 * - Change this file only when the top-level request pipeline changes.
 * - Add new routes in src/routes/actions.php or src/routes/pages.php.
 * ================================================
 */

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/routes/actions.php';
require __DIR__ . '/src/routes/pages.php';
