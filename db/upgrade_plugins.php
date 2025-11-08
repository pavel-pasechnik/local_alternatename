<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Upgrade steps for the local_alternatename plugin.
 *
 * @package    local_alternatename
 * @copyright  2025 Pavel Pasechnik
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executes upgrade steps for local_alternatename.
 *
 * @param int $oldversion the version number we are upgrading from.
 * @return bool always true when the upgrade completes without error.
 */
function xmldb_local_alternatename_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2025110800) {
        // For example: add a new table, field, or configuration setting.
        upgrade_plugin_savepoint(true, 2025110800, 'local', 'alternatename');
    }

    return true;
}
