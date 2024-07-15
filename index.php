<?php

/**
 * @file index.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Wrapper for the CRediT plugin.
 *
 */

require_once('CreditPlugin.php');
// return new \APP\plugins\generic\credit\CreditPlugin();
return new CreditPlugin();
